<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

/**
 * Public, non-persistent result of the WordPress theme import boundary.
 *
 * This is deliberately a preview contract. A successful preview means that
 * the archive crossed the bounded static conversion checks; it never means
 * that a native theme was installed or activated.
 */
final readonly class WordPressThemeImportPreview
{
    /**
     * @param array<string,mixed> $intake
     * @param array<string,mixed>|null $scan
     * @param array<string,mixed>|null $conversion
     * @param array<string,mixed>|null $assets
     * @param list<string> $nextActions
     * @param array<string,mixed>|null $update
     */
    public function __construct(
        public array $intake,
        public ?array $scan,
        public ?array $conversion,
        public ?array $assets,
        public bool $readyForReview,
        public array $nextActions,
        public ?array $update = null,
    ) {}

    /** @param array<string,mixed>|null $update */
    public function withUpdate(?array $update): self
    {
        return new self(
            $this->intake,
            $this->scan,
            $this->conversion,
            $this->assets,
            $this->readyForReview,
            $this->nextActions,
            $update,
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'schema' => 1,
            'workflow' => 'wordpress-theme-preview-v1',
            'install_supported' => $this->readyForReview,
            'install_requires_confirmation' => $this->readyForReview,
            'ready_for_review' => $this->readyForReview,
            'intake' => $this->intake,
            'scan' => $this->scan,
            'conversion' => $this->conversion,
            'assets' => $this->assets,
            'update' => $this->update,
            'next_actions' => $this->nextActions,
        ];
    }
}
