<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class ZammadSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $secret = $config->signingSecret;

        if ($secret === null || $secret === '') {
            return false;
        }

        $signature = $request->header($config->signatureHeaderName);

        if ($signature === null || $signature === '') {
            return false;
        }

        $signature = $this->normalizeSignature($signature);
        $expected = hash_hmac('sha1', $request->getContent(), $secret);

        return hash_equals(strtolower($expected), strtolower($signature));
    }

    /**
     * Zammad follows the WebSub convention: X-Hub-Signature: sha1=<hex-digest>
     */
    private function normalizeSignature(string $signature): string
    {
        if (str_contains($signature, '=')) {
            [, $digest] = explode('=', $signature, 2);

            return $digest;
        }

        return $signature;
    }
}
