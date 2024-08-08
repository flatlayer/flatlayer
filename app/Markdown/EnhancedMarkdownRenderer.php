<?php

namespace App\Markdown;

use Illuminate\Database\Eloquent\Model;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\MarkdownConverter;

class EnhancedMarkdownRenderer
{
    protected $environment;
    protected $converter;

    public function __construct(protected Model $model, Environment $environment = null)
    {
        $this->environment = $environment ?? $this->createDefaultEnvironment();
        $this->environment->addRenderer(Image::class, new CustomImageRenderer($this->model, $this->environment));
        $this->converter = new MarkdownConverter($this->environment);
    }

    protected function createDefaultEnvironment(): Environment
    {
        $environment = new Environment([
            'allow_unsafe_links' => false,
            'max_nesting_level' => 100,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        return $environment;
    }

    public function convertToHtml($markdown)
    {
        return $this->converter->convert($markdown);
    }
}
