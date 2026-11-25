<?php

declare(strict_types=1);

namespace SanctumManager\DataTransferObjects;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class TokenOptions
{
    public ?CarbonInterface $expiresAt = null;

    /**
     * @var array<int, string>|null
     */
    public ?array $allowedIps = null;

    /**
     * @var array<int, string>|null
     */
    public ?array $allowedOrigins = null;

    public ?int $maxUses = null;

    /**
     * @var array<string, mixed>
     */
    public array $metadata = [];

    public ?string $tokenPrefix = null;

    public static function make(): self
    {
        return new self;
    }

    public function expiresAt(?CarbonInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function expiresInMinutes(int $minutes): self
    {
        $this->expiresAt = Carbon::now()->addMinutes($minutes);

        return $this;
    }

    public function expiresInHours(int $hours): self
    {
        $this->expiresAt = Carbon::now()->addHours($hours);

        return $this;
    }

    public function expiresInDays(int $days): self
    {
        $this->expiresAt = Carbon::now()->addDays($days);

        return $this;
    }

    /**
     * @param  array<int, string>  $ips
     */
    public function allowIps(array $ips): self
    {
        $this->allowedIps = array_values($ips);

        return $this;
    }

    /**
     * @param  array<int, string>  $origins
     */
    public function allowOrigins(array $origins): self
    {
        $this->allowedOrigins = array_values($origins);

        return $this;
    }

    public function maxUses(?int $maxUses): self
    {
        $this->maxUses = $maxUses;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    public function withPrefix(?string $prefix): self
    {
        $this->tokenPrefix = $prefix;

        return $this;
    }
}
