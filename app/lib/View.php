<?php

declare(strict_types=1);

final class View
{
    private string $viewsPath;

    public function __construct(string $viewsPath)
    {
        $this->viewsPath = rtrim($viewsPath, DIRECTORY_SEPARATOR);
    }

    public function render(string $template, array $data = [], array $layoutData = []): void
    {
        $content = $this->renderPartial($template, $data);

        $title = (string)($layoutData['title'] ?? 'Supplier Portal');
        $auth = $layoutData['auth'] ?? null;
        $currentPage = (string)($layoutData['currentPage'] ?? 'home');

        require $this->resolve('view_layout');
    }

    private function renderPartial(string $template, array $data): string
    {
        $templatePath = $this->resolve($template);

        ob_start();
        extract($data, EXTR_SKIP);
        require $templatePath;

        return (string)ob_get_clean();
    }

    private function resolve(string $template): string
    {
        $path = $this->viewsPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $template) . '.php';

        if (!is_file($path)) {
            throw new RuntimeException("View template not found: {$template}");
        }

        return $path;
    }
}
