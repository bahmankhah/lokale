<?php

namespace Hsm\Lokale;

use Countable;
use Iterator;

class TranslationCollection implements Iterator, Countable
{
    private array $items = [];
    private int $index = 0;

    public function __construct($items = []) {
        $this->items = $items;
    }

    public static function fromArray($name ,array $translations) {
        $collection = new TranslationCollection();
        $tr = $translations;
        foreach($tr as $key => $value){
            if(is_array($value)){
                $collection->appendCollection(self::fromArray($name.'.'.$key, $value));
            }else{
                $collection->append(new TranslationKey($name.'.'.$key, $key));
            }
        }
        return $collection;
    }
        

    public function groupByFile(): array{
        $grouped = [];
        foreach($this->items as $item){
            $temp = explode('.', $item->getPath());
            $first = array_shift($temp);
            if(isset($grouped[$first])){
                $grouped[$first]->append($item);
            }else{
                $grouped[$first] = new TranslationCollection([$item]);
            }
        }
        return $grouped;
    }

    public function count(): int {
        return count($this->items);
    }

    public function append(TranslationKey $item): void {
        $current = $this->findPath($item->getPath());
        if($current){
            $current->setComments(array_merge($current->getComments(), $item->getComments()));
            $current->setFilePaths(array_merge($current->getFilePaths(), $item->getFilePaths()));
        }else{
            $this->items[] = $item;
        }
    }

    public function appendCollection(TranslationCollection $items): void {
        foreach ($items as $item) {
            $this->append($item);
        }
    }

    public function findPath(string $path): ?TranslationKey {
        foreach ($this->items as $item) {
            if ($item->getPath() === $path) {
                return $item;
            }
        }
        return null;
    }


    public function current():TranslationKey {
        return $this->items[$this->index];
    }

    public function key():string {
        return $this->items[$this->index]->getKey();
    }

    public function next():void {
        ++$this->index;
    }

    public function rewind():void {
        $this->index = 0;
    }

    public function valid():bool {
        return isset($this->items[$this->index]);
    }

}