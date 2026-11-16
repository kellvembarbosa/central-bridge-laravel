<?php

namespace Applu\CentralBridge\Data;

/**
 * @template T
 */
final readonly class Page
{
    /**
     * @param  list<T>  $data
     */
    public function __construct(
        public array $data,
        public int $page = 1,
        public int $perPage = 25,
        public bool $hasMore = false,
    ) {}

    /**
     * @param  callable(array): T|null  $item  maps each raw item into a data object
     */
    public static function fromArray(array $data, ?callable $item = null): self
    {
        $items = array_values($data['data'] ?? []);

        return new self(
            $item ? array_map(fn ($raw) => is_array($raw) ? $item($raw) : $raw, $items) : $items,
            (int) ($data['page'] ?? 1),
            (int) ($data['per_page'] ?? 25),
            (bool) ($data['has_more'] ?? false),
        );
    }

    public function toArray(): array
    {
        return [
            'data' => array_map(
                fn ($item) => is_object($item) && method_exists($item, 'toArray') ? $item->toArray() : $item,
                array_values($this->data),
            ),
            'page' => $this->page,
            'per_page' => $this->perPage,
            'has_more' => $this->hasMore,
        ];
    }
}
