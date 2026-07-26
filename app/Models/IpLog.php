<?php

/**
 * @property int $id
 * @property string $ip
 * @property int $userid
 * @property string|null $access
 * @property string|null $uri
 * @property int $count
 */
namespace App\Models;


use Illuminate\Database\Eloquent\Casts\Attribute;

class IpLog extends NexusModel
{
    /** @var  string */
    protected $table = 'iplog';

    /** @var  list<string> */
    protected $fillable = ['ip', 'userid', 'access', 'uri', 'count'];

    /** @return  \Illuminate\Database\Eloquent\Casts\Attribute<mixed, mixed> */
    protected function ipLocation(): Attribute
    {
        return new Attribute(
            get: fn (mixed $value, array $attributes) => $this->getIpLocation($attributes['ip'])
        );
    }

    /**
     * @param  string  $ip
     * @return  mixed
     */
    private function getIpLocation(string $ip)
    {
        $result = get_ip_location_from_geoip($ip);
        $out = $result['name'] ?? '';
        $suffix = [];
        if (!empty($result['city_en'])) {
            $suffix[] = $result['city_en'];
        }
        if (!empty($result['country_en'])) {
            $suffix[] = $result['country_en'];
        }
        if (!empty($result['continent_en'])) {
            $suffix[] = $result['continent_en'];
        }
        if (!empty($suffix)) {
            $out .= " " . implode(', ', $suffix);
        }
        return $out;
    }
}
