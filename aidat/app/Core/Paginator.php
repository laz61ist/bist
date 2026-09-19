<?php

declare(strict_types=1);

namespace Aidat\Core;

final class Paginator
{
    public readonly int $page;
    public readonly int $perPage;
    public readonly int $total;
    public readonly int $lastPage;
    public readonly int $offset;

    public function __construct(int $total, int $page = 1, int $perPage = 25)
    {
        $this->total = max(0, $total);
        $this->perPage = max(1, min($perPage, 500));
        $this->lastPage = max(1, (int) ceil($this->total / $this->perPage));
        $this->page = max(1, min($page, $this->lastPage));
        $this->offset = ($this->page - 1) * $this->perPage;
    }

    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->offset + $this->perPage);
    }

    /** @return list<int|string> sayfa numaraları ve '…' */
    public function window(): array
    {
        $pages = [];
        $last = $this->lastPage;
        $cur = $this->page;
        if ($last <= 7) {
            return range(1, $last);
        }
        $pages[] = 1;
        if ($cur > 3) {
            $pages[] = '…';
        }
        for ($i = max(2, $cur - 1); $i <= min($last - 1, $cur + 1); $i++) {
            $pages[] = $i;
        }
        if ($cur < $last - 2) {
            $pages[] = '…';
        }
        $pages[] = $last;
        return $pages;
    }
}
