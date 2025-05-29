<?php

namespace Hsm\Lokale;

class TranslationKey
{
    private $key;
    private $path;
    private $filePaths;
    private $comments;
    private $translation;
    private $translationArray;
    public function __construct($path, $key = null, $filePaths = [], $comments = [], $translation = '', $translationArray = [])
    {
        $this->path = $path;
        $this->translation = $translation;
        $this->key = $key;
        $this->filePaths = $filePaths;
        $this->comments = $comments;
        $this->translationArray = $translationArray;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getFilePaths()
    {
        return $this->filePaths;
    }

    public function getComments()
    {
        return $this->comments;
    }

    public function getTranslation()
    {
        return $this->translation;
    }
    public function getTranslationArray()
    {
        return $this->translationArray;
    }

    public function setTranslationArray($translationArray)
    {
        $this->translationArray = $translationArray;
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    public function setFilePaths($filePaths)
    {
        $this->filePaths = $filePaths;
    }

    public function addComment($commentValue)
    {
        if (!in_array($commentValue, $this->comments)) {
            $this->comments[] = $commentValue;
        }
    }

    public function addFilePath($filePath)
    {
        if (!in_array($filePath, $this->filePaths)) {
            $this->filePaths[] = $filePath;
        }
    }

    public function setComments($comments)
    {
        $this->comments = $comments;
    }

    public function setTranslation($translation)
    {
        $this->translation = $translation;
    }
}
