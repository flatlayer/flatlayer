<?php

namespace App\Traits;

use App\Models\Tag;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasTags
{
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function scopeWithAllTags(Builder $query, array|string $tagNames): Builder
    {
        $tagNames = is_array($tagNames) ? $tagNames : [$tagNames];

        return $query->where(function (Builder $query) use ($tagNames) {
            foreach ($tagNames as $tagName) {
                $query->whereHas('tags', fn (Builder $q) => $q->where('name', $tagName));
            }
        });
    }

    public function scopeWithAnyTags(Builder $query, array|string $tagNames): Builder
    {
        $tagNames = is_array($tagNames) ? $tagNames : [$tagNames];

        return $query->whereHas('tags', fn (Builder $q) => $q->whereIn('name', $tagNames));
    }

    public function attachTag(string $tagName): static
    {
        $tag = $this->getTagModels($tagName)->first();
        $this->tags()->syncWithoutDetaching([$tag->id]);

        return $this;
    }

    public function attachTags(array|string $tagNames): static
    {
        $tags = $this->getTagModels($tagNames);
        $this->tags()->syncWithoutDetaching($tags->pluck('id')->all());

        return $this;
    }

    public function detachTags(array|string $tagNames): static
    {
        $tags = $this->getTagModels($tagNames);
        $this->tags()->detach($tags->pluck('id'));

        return $this;
    }

    public function syncTags(array|string $tagNames): static
    {
        $tags = $this->getTagModels($tagNames);
        $this->tags()->sync($tags->pluck('id'));
        $this->load('tags');

        return $this;
    }

    protected function getTagModels(Arrayable|array|string $tagNames): Collection
    {
        $tagNames = match (true) {
            $tagNames instanceof Arrayable => $tagNames->toArray(),
            is_string($tagNames) => [$tagNames],
            default => $tagNames,
        };

        return collect($tagNames)
            ->map(fn (string $tagName) => Tag::firstOrCreate(['name' => $tagName]))
            ->unique('id');
    }
}
