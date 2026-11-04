<?php

declare(strict_types=1);

namespace App\Partner\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

#[ORM\Entity]
#[ORM\Table(name: 'app_partner_category')]
#[ORM\Index(name: 'IDX_partner_category_partner', columns: ['partner_code', 'enabled'])]
class PartnerCategory implements ResourceInterface
{
    public const STATE_IDLE = 'idle';

    public const STATE_RUNNING = 'running';

    public const STATE_PAUSED = 'paused';

    public const STATE_DONE = 'done';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'partner_code', type: 'string', length: 32)]
    private string $partnerCode;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(name: 'start_url', type: 'text', nullable: true)]
    private ?string $startUrl;

    #[ORM\Column(name: 'url_keywords', type: 'string', length: 255, nullable: true)]
    private ?string $urlKeywords;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $enabled = true;

    /** @var list<string>|null */
    #[ORM\Column(name: 'pending_urls', type: 'json', nullable: true)]
    private ?array $pendingUrls = null;

    /** @var list<string> */
    #[ORM\Column(name: 'visited_urls', type: 'json')]
    private array $visitedUrls = [];

    #[ORM\Column(name: 'pages_fetched', type: 'integer', options: ['default' => 0])]
    private int $pagesFetched = 0;

    #[ORM\Column(name: 'fetch_requested_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $fetchRequestedAt = null;

    #[ORM\Column(name: 'finished_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct(string $partnerCode, string $name, ?string $startUrl, ?string $urlKeywords)
    {
        $this->partnerCode = $partnerCode;
        $this->name = $name;
        $this->startUrl = $startUrl;
        $this->urlKeywords = $urlKeywords;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPartnerCode(): string
    {
        return $this->partnerCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStartUrl(): ?string
    {
        return $this->startUrl;
    }

    public function getUrlKeywords(): ?string
    {
        return $this->urlKeywords;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /** @return list<string>|null */
    public function getPendingUrls(): ?array
    {
        return $this->pendingUrls;
    }

    public function getPagesFetched(): int
    {
        return $this->pagesFetched;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function isActive(): bool
    {
        return $this->enabled && $this->fetchRequestedAt !== null;
    }

    public function getState(): string
    {
        return match (true) {
            $this->fetchRequestedAt !== null => $this->enabled ? self::STATE_RUNNING : self::STATE_PAUSED,
            $this->finishedAt !== null => self::STATE_DONE,
            default => self::STATE_IDLE,
        };
    }

    public function matchesUrl(string $url): bool
    {
        $keywords = preg_split('/\s+/', mb_strtolower(trim((string) $this->urlKeywords)), -1, \PREG_SPLIT_NO_EMPTY);
        if ($keywords === false || $keywords === []) {
            return false;
        }

        $path = (string) parse_url($url, \PHP_URL_PATH);
        $segment = mb_strtolower(substr($path, (int) strrpos($path, '/') + 1));
        foreach ($keywords as $keyword) {
            if (!str_contains($segment, $keyword)) {
                return false;
            }
        }

        return true;
    }

    public function requestFetch(\DateTimeImmutable $notFinishedSince): bool
    {
        if ($this->fetchRequestedAt !== null) {
            return true;
        }
        if ($this->finishedAt !== null && $this->finishedAt > $notFinishedSince) {
            return false;
        }
        $this->fetchRequestedAt = new \DateTimeImmutable();

        return true;
    }

    /** @param list<string> $urls */
    public function begin(array $urls): void
    {
        $this->pendingUrls = array_values(array_unique($urls));
        $this->visitedUrls = [];
        $this->pagesFetched = 0;
        $this->finishedAt = null;
    }

    public function nextUrl(): ?string
    {
        return $this->pendingUrls[0] ?? null;
    }

    /** @param list<string> $discovered */
    public function markVisited(string $url, array $discovered = []): void
    {
        $pending = array_values(array_filter($this->pendingUrls ?? [], static fn (string $pendingUrl): bool => $pendingUrl !== $url));
        $this->visitedUrls[] = $url;
        foreach ($discovered as $discoveredUrl) {
            if (!in_array($discoveredUrl, $this->visitedUrls, true) && !in_array($discoveredUrl, $pending, true)) {
                $pending[] = $discoveredUrl;
            }
        }
        $this->pendingUrls = $pending;
        ++$this->pagesFetched;
    }

    public function finish(): void
    {
        $this->pendingUrls = null;
        $this->visitedUrls = [];
        $this->fetchRequestedAt = null;
        $this->finishedAt = new \DateTimeImmutable();
    }
}
