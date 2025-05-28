<?php
namespace Hsm\Lokale;

use PhpParser\Comment;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class FileService
{
    public function createLanguageFile($name, $locale, $array, $output, &$keyComments = [], $comment = false)
    {
        $langMain = base_path($output);
        $langFile = sprintf('%s/%s/%s.php', $langMain, $locale, $name);
        if (!file_exists(dirname($langFile))) {
            mkdir(dirname($langFile), 0777, true);
        }
        if (!file_exists($langFile)) {
            $content = [];
        } else {
            $content = require $langFile;
            if (!is_array($content)) {
                $content = [];
            }
        }
        $array = $this->recursiveMerge($content, $array, $keyComments, $comment);
        $this->createPhpArrayFile($langFile, $array, $keyComments);
    }

    public function createPhpArrayFile($filePath, array $data, array &$keyComments)
    {
        // Export the array using var_export
        $exported = "<?php\nreturn " . var_export($data, true) . ";\n";

        // Parse the exported code into an AST
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($exported);

        // Create a visitor to convert array() to [] and add comments for specific keys
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new class($keyComments) extends NodeVisitorAbstract {
            private $keyComments;

            public function __construct(array &$keyComments)
            {
                $this->keyComments = &$keyComments;
            }

            public function enterNode(Node $node)
            {
                // Convert ArrayNode to use short syntax
                if ($node instanceof Node\Expr\Array_) {
                    $node->setAttribute('kind', Node\Expr\Array_::KIND_SHORT);
                }

                // Add comments to array items with specified keys
                if ($node instanceof Node\Expr\ArrayItem && $node->key !== null) {
                    // Convert key to string (handles string or numeric keys)
                    $key = $node->key instanceof Node\Scalar\String_
                        ? $node->key->value
                        : ($node->key instanceof Node\Scalar\LNumber
                            ? (string)$node->key->value
                            : null);

                    // Add comment if the key exists in $keyComments
                    if ($key !== null && isset($this->keyComments[$key])) {
                            $node->setAttribute('comments', [
                                new Comment("// " . implode(' | ', $this->keyComments[$key]))
                            ]);
                        
                    }
                }

                return null;
            }
        });

        // Traverse and modify the AST
        $ast = $traverser->traverse($ast);

        // Pretty print the modified AST with tree-like formatting
        $prettyPrinter = new class extends Standard {
            public function prettyPrintFile(array $nodes): string
            {
                $code = $this->prettyPrint($nodes);
                return "<?php\n/**\n * Generated with hsm/lokale\n */\n" . $code;
            }

            protected function pExpr_Array(Node\Expr\Array_ $node): string
            {
                $syntax = $node->getAttribute('kind', Node\Expr\Array_::KIND_LONG);
                $isShort = $syntax === Node\Expr\Array_::KIND_SHORT;
                $items = $node->items;

                if (empty($items)) {
                    return $isShort ? '[]' : 'array()';
                }

                $result = $isShort ? "[\n" : "array(\n";
                $indent = str_repeat('    ', $this->getIndentLevel() + 1);

                foreach ($items as $index => $item) {
                    $comments = $item->getAttribute('comments', []);
                    if ($comments) {
                        foreach ($comments as $comment) {
                            $result .= $indent . $comment->getReformattedText() . $this->nl;
                        }
                    }
                    $result .= $indent . $this->p($item);
                    $result .= isset($items[$index + 1]) ? ',' : '';
                    $result .= "\n";
                }

                $result .= str_repeat('    ', $this->getIndentLevel()) . ($isShort ? ']' : ')');
                return $result;
            }

            private function getIndentLevel(): int
            {
                return $this->indentLevel ?? 0;
            }
        };

        $content = $prettyPrinter->prettyPrintFile($ast);

        file_put_contents($filePath, $content);
    }

    public function addComment(&$comments,$key, $commentValue){
        if(isset($comments[$key])){
            if(!in_array($commentValue, $comments[$key])){
                $comments[$key][] = $commentValue;
            }
        }else{
            $comments[$key] = [$commentValue];
        }
    }

    public function recursiveMerge(array $array1, array $array2, array &$comments, $comment = false)
    {
        foreach ($array2 as $key => $value) {
            if (isset($array1[$key]) && is_array($array1[$key]) && is_array($value)) {
                $array1[$key] = $this->recursiveMerge($array1[$key], $value, $comments, $comment);
            } elseif (!isset($array1[$key]) || $array1[$key] === '') {
                $array1[$key] = $value;
                if($comment){
                    $this->addComment($comments,$key,"@TODO Add translation");
                }
            }
        }
        return $array1;
    }

    public function makeArray($path, &$arr)
    {
        $parts = explode('.', $path);

        if (empty($path)) {
            return;
        }

        $key = array_shift($parts);
        if (!isset($arr[$this->makeKey($key)])) {
            $arr[$this->makeKey($key)] = [];
        }

        if (!empty($parts)) {
            $this->makeArray(implode('.', $parts), $arr[$this->makeKey($key)]); // Recurse on the rest
        } else {
            $arr[$this->makeKey($key)] = $this->makeDefaultPlaceholder($key);
        }
    }

    public function makeDefaultPlaceholder($key)
    {
        return str_replace("_", " ", ucfirst($key));
    }

    public function makeKey($str)
    {
        return strtolower(str_replace(' ', "_", trim($str)));
    }

    public function extractTranslationKeysWithParser($filePath, &$keyComments)
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $content = file_get_contents($filePath);
        $keys = [];

        if (str_ends_with($filePath, '.blade.php')) {
            // Use regex to find translation function calls
            preg_match_all(
                '/(?:__|trans_choice|trans)\s*\((?:[^()]+|\([^()]*\))*[^()]*\)/',
                $content,
                $matches
            );

            foreach ($matches[0] as $match) {
                $phpSnippet = "<?php $match;";
                try {
                    $ast = $parser->parse($phpSnippet);
                    $traverser = new NodeTraverser();

                    $traverser->addVisitor(new class($keys) extends NodeVisitorAbstract {
                        public $keys;

                        public function __construct(&$keys)
                        {
                            $this->keys = &$keys;
                        }

                        public function enterNode(Node $node)
                        {
                            if (
                                $node instanceof Node\Stmt\Expression &&
                                $node->expr instanceof FuncCall &&
                                $node->expr->name instanceof Node\Name &&
                                in_array($node->expr->name->toString(), ['__', 'trans_choice', 'trans'])
                            ) {

                                $keyArg = $node->expr->args[0]->value;
                                if(isset($node->expr->args[1]?->value)){
                                    var_dump($node->expr->args[1]->value);
                                    die();
                                }
                                if ($keyArg instanceof Node\Scalar\String_) {
                                    $this->keys[] = $keyArg->value;
                                } else {
                                    $this->keys[] = $this->prettyPrintExpr($keyArg);
                                }
                            }
                        }

                        private function prettyPrintExpr(Node $expr): string
                        {
                            $printer = new Standard();
                            $pretty = $printer->prettyPrintExpr($expr);
                            return trim($pretty, '"\'');
                        }
                    });

                    $traverser->traverse($ast);
                } catch (\PhpParser\Error $e) {
                    // skip invalid PHP
                }
            }
        } else {
            // Normal PHP file, parse entire content
            try {
                $ast = $parser->parse($content);
                $traverser = new NodeTraverser();

                $traverser->addVisitor(new class($keys, $this, $filePath, $keyComments) extends NodeVisitorAbstract {
                    public $keys;
                    public $fileService;
                    public $file;
                    public $keyComments;

                    public function __construct(&$keys, FileService $fileService, $filePath, &$keyComments)
                    {
                        $this->file = $filePath;
                        $this->keys = &$keys;
                        $this->fileService = $fileService;
                        $this->keyComments = &$keyComments;
                    }

                    public function enterNode(Node $node)
                    {
                        if (
                            $node instanceof FuncCall &&
                            $node->name instanceof Node\Name &&
                            in_array($node->name->toString(), ['__', 'trans_choice', 'trans'])
                        ) {
                            $keyArg = $node->args[0]->value;
                            $key = null;
                            if ($keyArg instanceof Node\Scalar\String_) {
                                $key =$this->fileService->makeKey($keyArg->value);
                            } else {
                                $key = $this->fileService->makeKey($this->prettyPrintExpr($keyArg));
                            }
                            $this->keys[] = $key;
                            if($node->name->toString() == '__' && isset($node->args[1]?->value?->items)){
                                $vars = [];
                                foreach ($node->args[1]?->value?->items as $item) {
                                    $vars[] = ':'.$item->key->value;
                                }
                                if(!empty($vars)){
                                    $parts = explode('.', $key);
                                    $comment = '@TODO Translate variables ';
                                    $comment .= implode(', ', $vars);
                                    $comment .= ' in file: ' . str_replace(base_path(), '', $this->file) . ' on line ' . $node->getLine();
                                    $this->fileService->addComment($this->keyComments, end($parts), $comment);
                                }
                            }
                        }
                    }

                    private function prettyPrintExpr(Node $expr): string
                    {
                        $printer = new Standard();
                        $pretty = $printer->prettyPrintExpr($expr);
                        return trim($pretty, '"\'');
                    }
                });

                $traverser->traverse($ast);
            } catch (Error $e) {
                // skip invalid PHP file
            }
        }

        return array_unique($keys);
    }


    public function extractTranslationKeys($filePath)
    {
        $content = file_get_contents($filePath);
        $pattern = '/
            __\(\s*[\'"]([^\'"]+)[\'"]\s*\)     
            |                                   
            trans_choice\(\s*[\'"]([^\'"]+)[\'"]\s*,
        /x';

        preg_match_all($pattern, $content, $matches);

        return array_unique(array_filter(array_merge($matches[1], $matches[2])));
    }


    public function getFiles($path, &$files = [])
    {
        if (is_file($path)) {
            $files[] = $path;
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);

        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->getFiles($path . DIRECTORY_SEPARATOR . $entry, $files);
            }
        }

        return $files;
    }
}
