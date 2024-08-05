<?php

namespace App\Services;

use Illuminate\Support\Str;

class ModelResolverService
{
    protected array $namespaces = [
        'App\\Models\\',
    ];

    public function addNamespace(string $namespace): void
    {
        $namespace = rtrim($namespace, '\\') . '\\';
        if (!in_array($namespace, $this->namespaces)) {
            $this->namespaces[] = $namespace;
        }
    }

    public function resolve(string $modelSlug): ?string
    {
        $singularModelName = Str::studly(Str::singular($modelSlug));

        foreach ($this->namespaces as $namespace) {
            $modelClass = $namespace . $singularModelName;
            if (class_exists($modelClass)) {
                return $modelClass;
            }

            // Check for case-insensitive match
            foreach (get_declared_classes() as $declaredClass) {
                if (strcasecmp($modelClass, $declaredClass) === 0) {
                    return $declaredClass;
                }
            }
        }

        return null;
    }
}
