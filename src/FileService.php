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
    public $placeholder;
    public $comment;
    public $output;
    public $default;
    public function __construct($output = 'lang', $comment = false, $placeholder = true, $default = 'default')
    {
        $this->comment = $comment;
        $this->output = $output;
        $this->placeholder = $placeholder;
        $this->default = $default;
    }

    public function createLanguageFileFromCollection($name, $locale, $translationCollection)
    {
        $langMain = base_path($this->output);
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
        foreach ($translationCollection as $key) {
            $arr = [];
            $temp = explode('.', $key->getPath());
            $first = array_shift($temp);
            $this->makeArray(implode('.', $temp), $arr, $key);
            if (empty($arr)) {
                if ($this->placeholder) {
                    $value = $this->makeDefaultPlaceholder($first);
                } else {
                    $value = '';
                }
                $key->setTranslationArray([$this->makeKey($first) => $value]);
            } else {
                $key->setTranslationArray($arr);
            }
            $content = $this->recursiveMerge($content, $key->getTranslationArray());
        }
        $this->createPhpArrayFile($langFile, $content, $translationCollection);
    }


    public function createLanguageFile($name, $locale, $array)
    {
        $langMain = base_path($this->output);
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

    public function createPhpArrayFile($filePath, array $data, ?TranslationCollection $translationCollection = null)
    {
        $exported = "<?php\nreturn " . var_export($data, true) . ";\n";

        $fileName = basename($filePath, '.php');

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($exported);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new class( $fileName, $this->comment, $translationCollection = null) extends NodeVisitorAbstract {
            public $translationCollection;
            public $parentStack = [];
            public $fileName;
            public $comment;
            public function __construct($fileName, $comment = false, $translationCollection = null)
            {
                $this->translationCollection = $translationCollection;
                $this->fileName = $fileName;
                $this->comment = $comment;
            }

            public function leaveNode(Node $node)
            {
                array_pop($this->parentStack);
                return null;
            }

            public function enterNode(Node $node)
            {
                if (!empty($this->parentStack)) {
                    $node->setAttribute('parent', end($this->parentStack));
                }
                $this->parentStack[] = $node;

                if ($node instanceof Node\Expr\Array_) {
                    $node->setAttribute('kind', Node\Expr\Array_::KIND_SHORT);
                }


                if ($node instanceof Node\Expr\ArrayItem && $node->key !== null) {
                    $key = $node->key instanceof Node\Scalar\String_
                        ? $node->key->value
                        : ($node->key instanceof Node\Scalar\LNumber
                            ? (string)$node->key->value
                            : null);

                    if ($key !== null && $this->translationCollection) {
                        if ($node->value instanceof Node\Scalar\String_) {
                            $translationKey = $this->translationCollection->findPath($this->makePath($node));

                            if ($translationKey) {
                                if ($this->comment) {
                                    $comments = [];
                                    foreach ($translationKey->getComments() as $comment) {
                                        if (!str_starts_with($comment, '//')) {
                                            $comment = "// $comment";
                                        }
                                        $comments[] = new Comment($comment);
                                    }
                                    $node->setAttribute('comments', $comments);
                                }
                            }
                        }
                    }
                }

                return null;
            }

            public function makePath($node)
            {
                $path = [];
                while ($node !== null) {
                    if ($node instanceof Node\Expr\ArrayItem && $node->key !== null) {
                        $key = $node->key instanceof Node\Scalar\String_
                            ? $node->key->value
                            : ($node->key instanceof Node\Scalar\LNumber
                                ? (string)$node->key->value
                                : null);
                        $path[] = $key;
                    }
                    $node = $node->getAttribute('parent', null);
                }
                return $this->fileName . '.' . implode('.', array_reverse($path));
            }
        });

        $ast = $traverser->traverse($ast);

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


    public function recursiveMerge(array $array1, array $array2)
    {
        foreach ($array2 as $key => $value) {
            if (isset($array1[$key]) && is_array($array1[$key]) && is_array($value)) {
                $array1[$key] = $this->recursiveMerge($array1[$key], $value);
            } elseif (!isset($array1[$key]) || $array1[$key] === '') {
                $array1[$key] = $value;
            }
        }
        return $array1;
    }

    public function makeArray($path, &$arr, $translationKey)
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
            $this->makeArray(implode('.', $parts), $arr[$this->makeKey($key)], $translationKey); // Recurse on the rest
        } else {
            if($this->placeholder){
                $arr[$this->makeKey($key)] = $this->makeDefaultPlaceholder($key);
            }else{
                $arr[$this->makeKey($key)] = '';
                $translationKey->addComment("@TODO Add translation");
            }
        }
    }

    public function makeDefaultPlaceholder($key)
    {
        return str_replace("_", " ", ucfirst($key));
    }

    public function makeKey($str)
    {
        return strtolower(str_replace(' ', "_", trim($str, "\"'.")));
    }

    public function extractTranslationKeysWithParser($filePath)
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $content = file_get_contents($filePath);
        $keys = new TranslationCollection();

        if (str_ends_with($filePath, '.blade.php')) {
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

                    $traverser->addVisitor(new class($keys, $filePath, $this) extends NodeVisitorAbstract {
                        public $keys;
                        public $file;
                        public $fileService;

                        public function __construct(&$keys, $filePath, FileService $fileService)
                        {
                            $this->fileService = $fileService;
                            $this->keys = &$keys;
                            $this->file = $filePath;
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
                                if ($keyArg instanceof Node\Scalar\String_) {
                                    $key = $this->fileService->makeKey($keyArg->value);
                                    if ($key !== $keyArg->value) {
                                        $node->expr->args[0]->value = new Node\Scalar\String_($key);
                                    }
                                } else {
                                    $key = $this->fileService->makeKey($this->prettyPrintExpr($keyArg));
                                }
                                $translationKey = new TranslationKey($this->fileService->createPath($key));
                                $translationKey->setKey($this->fileService->findKeyFromPath($key));
                                $translationKey->addFilePath(str_replace(base_path(), '', $this->file) . ':' . $node->getLine());
                                if ($node->expr->name->toString() == '__' && isset($node->expr->args[1]?->value?->items) && $this->fileService->comment) {
                                    $vars = [];
                                    foreach ($node->expr->args[1]?->value?->items as $item) {
                                        $vars[] = ':' . $item->key->value;
                                    }
                                    if (!empty($vars)) {
                                        $comment = '@TODO Translate variables ';
                                        $comment .= implode(', ', $vars);
                                        $comment .= ' in file: ' . str_replace(base_path(), '', $this->file) . ' on line ' . $node->getLine();
                                        $translationKey->addComment($comment);
                                    }
                                }
                                $this->keys->append($translationKey);
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
            try {
                $ast = $parser->parse($content);
                $traverser = new NodeTraverser();

                $traverser->addVisitor(new class($keys, $this, $filePath) extends NodeVisitorAbstract {
                    public $keys;
                    public $fileService;
                    public $file;

                    public function __construct(&$keys, FileService $fileService, $filePath)
                    {
                        $this->file = $filePath;
                        $this->keys = &$keys;
                        $this->fileService = $fileService;
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
                                $key = $this->fileService->makeKey($keyArg->value);
                                if ($key !== $keyArg->value) {
                                    $node->args[0]->value = new Node\Scalar\String_($key);
                                }
                            } else {
                                $key = $this->fileService->makeKey($this->prettyPrintExpr($keyArg));
                            }
                            $translationKey = new TranslationKey($this->fileService->createPath($key));
                            $translationKey->setKey($this->fileService->findKeyFromPath($key));
                            $translationKey->addFilePath(str_replace(base_path(), '', $this->file) . ':' . $node->getLine());
                            if ($node->name->toString() == '__' && isset($node->args[1]?->value?->items) && $this->fileService->comment) {
                                $vars = [];
                                foreach ($node->args[1]?->value?->items as $item) {
                                    $vars[] = ':' . $item->key->value;
                                }
                                if (!empty($vars)) {
                                    $comment = '@TODO Translate variables ';
                                    $comment .= implode(', ', $vars);
                                    $comment .= ' in file: ' . str_replace(base_path(), '', $this->file) . ' on line ' . $node->getLine();
                                    $translationKey->addComment($comment);
                                }
                            }
                            $this->keys->append($translationKey);
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

        return $keys;
    }

    public function findKeyFromPath($path)
    {
        $parts = explode('.', $path);
        return end($parts);
    }

    public function createPath($path)
    {
        $parts = explode('.', $path);
        if (count($parts) > 1) {
            return $path;
        } else {
            return $this->default . '.' . $path;
        }
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
