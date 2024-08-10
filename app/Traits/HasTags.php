<?php

namespace App\Traits;

use App\Models\Tag;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

trait HasTags
{
    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function scopeWithAllTags(Builder $query, $tagNames): Builder
    {
        $tagNames = is_array($tagNames) ? $tagNames : [$tagNames];

        foreach ($tagNames as $tagName) {
            $query->whereHas('tags', function (Builder $query) use ($tagName) {
                $query->where('name', $tagName);
            });
        }

        return $query;
    }

    public function scopeWithAnyTags(Builder $query, $tagNames): Builder
    {
        $tagNames = is_array($tagNames) ? $tagNames : [$tagNames];

        return $query->whereHas('tags', function (Builder $query) use ($tagNames) {
            $query->whereIn('name', $tagNames);
        });
    }

    public function attachTag($tagName): static
    {
        $tag = $this->getTagModels($tagName)->first();
        $this->tags()->syncWithoutDetaching([$tag->id]);
        return $this;
    }

    public function attachTags($tagNames): static
    {
        $tags = $this->getTagModels($tagNames);
        $tagIds = $tags->pluck('id')->all();
        $this->tags()->syncWithoutDetaching($tagIds);
        return $this;
    }

    public function detachTags($tagNames): static
    {
        $tags = $this->getTagModels($tagNames);
        $this->tags()->detach($tags);
        return $this;
    }

    public function syncTags($tagNames): static
    {
        $tags = $this->getTagModels($tagNames);
        $this->tags()->sync($tags);
        return $this;
    }

    protected function getTagModels(Arrayable|array $tagNames): Collection
    {
        return collect($tagNames)->map(function ($tagName) {
            return Tag::firstOrCreate(['name' => $tagName]);
        });
    }
}
