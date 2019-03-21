<?php
namespace JdcloudSign\Signature;

/**
 * Provides signature calculation for SignatureV4.
 */
trait SignatureTrait
{
    /** @var array Cache of previously signed values */
    private $cache = [];

    /** @var int Size of the hash cache */
    private $cacheSize = 0;

    private function createScope($shortDate, $region, $service)
    {
        return "$shortDate/$region/$service/jdcloud2_request";
    }

    private function getSigningKey($shortDate, $region, $service, $secretKey)
    {
        $k = $shortDate . '_' . $region . '_' . $service . '_' . $secretKey;

        if (!isset($this->cache[$k])) {
            // Clear the cache when it reaches 50 entries
            if (++$this->cacheSize > 50) {
                $this->cache = [];
                $this->cacheSize = 0;
            }

            $dateKey = hash_hmac(
                'sha256',
                $shortDate,
                "JDCLOUD2{$secretKey}",
                true
            );
//             var_dump(bin2hex($dateKey));
            $regionKey = hash_hmac('sha256', $region, $dateKey, true);
//             var_dump(bin2hex($regionKey));
            $serviceKey = hash_hmac('sha256', $service, $regionKey, true);
//             var_dump(bin2hex($serviceKey));
            $this->cache[$k] = hash_hmac(
                'sha256',
                'jdcloud2_request',
                $serviceKey,
                true
                );
//             var_dump(bin2hex($this->cache[$k]));
        }

        return $this->cache[$k];
    }
}
