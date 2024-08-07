<?php

namespace App\Markdown;

use Illuminate\Database\Eloquent\Model;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\MarkdownConverter;

class CustomMarkdownRenderer
{
    protected $environment;
    protected $converter;

    public function __construct(protected Model $model)
    {
        $this->environment = new Environment();
        $this->environment->addExtension(new CommonMarkCoreExtension());
        $this->environment->addRenderer(Image::class, new EnhancedMarkdownRenderer($this->model));
        $this->converter = new MarkdownConverter($this->environment);
    }

    public function convertToHtml($markdown)
    {
        return $this->converter->convert($markdown);
    }
}
