<?php

namespace Hsm\Lokale\Console;

use Illuminate\Console\Command;
use PhpParser\Comment;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class MakeLocale extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locale:make {--locale=} {--src=app} {--default=default} {--comment} {--output=lang}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private $keyComments = [];
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $locale = $this->option('locale');
        $default = $this->option('default');
        if (!$locale) {
            $locale = config('app.locale');
        }

        $files = [];
        $appDir = $this->option('src');
        $appDir = realpath($appDir);
        $this->getFiles($appDir, $files);
        $allKeys = [];
        foreach ($files as $file) {
            $allKeys = array_merge($allKeys, $this->extractTranslationKeysWithParser($file));
        }
        $allKeys = array_unique($allKeys);
        // print_r($allKeys);
        // die();

        foreach ($allKeys as $key) {
            $arr = [];
            $temp = explode('.', $key);
            $first = array_shift($temp);
            $this->makeArray(implode('.', $temp), $arr);
            if (empty($arr)) {
                $this->createLanguageFile($default, $locale, [$this->makeKey($first) => $first]);
            } else {
                $this->createLanguageFile($first, $locale, $arr);
            }
        }
    }

    public function makeKey($str)
    {
        return strtolower(str_replace(' ', "_", trim($str)));
    }

    private function createLanguageFile($name, $locale, $array)
    {
        $langMain = base_path($this->option('output'));
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
        $array = $this->recursiveMerge($content, $array);
        $this->createPhpArrayFile($langFile, $array);
    }

    private function createPhpArrayFile($filePath, array $data)
    {
        // Export the array using var_export
        $exported = "<?php\nreturn " . var_export($data, true) . ";\n";

        // Parse the exported code into an AST
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($exported);

        // Create a visitor to convert array() to [] and add comments for specific keys
        $traverser = new NodeTraverser();
        $keyComments = $this->keyComments;
        $traverser->addVisitor(new class($keyComments) extends NodeVisitorAbstract {
            private $keyComments;

            public function __construct(array $keyComments)
            {
                $this->keyComments = $keyComments;
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

    public function addComment($key, $commentValue){
        if(isset($this->keyComments[$key])){
            if(!in_array($commentValue, $this->keyComments[$key])){
                $this->keyComments[$key][] = $commentValue;
            }
        }else{
            $this->keyComments[$key] = [$commentValue];
        }
    }

    private function recursiveMerge(array $array1, array $array2)
    {
        foreach ($array2 as $key => $value) {
            if (isset($array1[$key]) && is_array($array1[$key]) && is_array($value)) {
                $array1[$key] = $this->recursiveMerge($array1[$key], $value);
            } elseif (!isset($array1[$key]) || $array1[$key] === '') {
                $array1[$key] = $value;
                if($this->option('comment')){
                    $this->addComment($key,"@TODO Add translation");
                }
            }
        }
        return $array1;
    }

    private function makeArray($path, &$arr)
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

    private function makeDefaultPlaceholder($key)
    {
        return str_replace("_", " ", ucfirst($key));
    }

    private function extractTranslationKeysWithParser($filePath)
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

                $traverser->addVisitor(new class($keys, $this, $filePath) extends NodeVisitorAbstract {
                    public $keys;
                    public $makeLocale;
                    public $file;

                    public function __construct(&$keys, MakeLocale $makeLocale, $filePath)
                    {
                        $this->file = $filePath;
                        $this->keys = &$keys;
                        $this->makeLocale = $makeLocale;
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
                                $key =$this->makeLocale->makeKey($keyArg->value);
                            } else {
                                $key = $this->makeLocale->makeKey($this->prettyPrintExpr($keyArg));
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
                                    $this->makeLocale->addComment(end($parts), $comment);
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


    private function extractTranslationKeys($filePath)
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


    private function getFiles($path, &$files = [])
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
