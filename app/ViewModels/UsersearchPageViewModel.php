<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the user search page.
 *
 * Returned by UsersearchPageService::build().
 */
final class UsersearchPageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $form
     */
    public function __construct(
        public readonly string $requestUri,
        public readonly bool $showHelp,
        public readonly array $form,
        public readonly bool $hasResults,
        public readonly string $resultsHtml,
        public readonly string $resultsError,
        public readonly string $pagemenu,
        public readonly string $browsemenu,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'requestUri' => $this->requestUri,
            'showHelp' => $this->showHelp,
            'form' => $this->form,
            'hasResults' => $this->hasResults,
            'resultsHtml' => $this->resultsHtml,
            'resultsError' => $this->resultsError,
            'pagemenu' => $this->pagemenu,
            'browsemenu' => $this->browsemenu,
        ];
    }
}
