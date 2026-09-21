<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Config\SiteConfig;
use App\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;

/**
 * W1-06: Validation for legacy POST /takeupload.
 *
 * Rules mirror the actual upload form field names (uplver, offer, file) and
 * the service-level contract: name is optional — UploadService falls back to
 * the torrent's own info.name when the field is left empty.
 */
class TorrentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|nullable|string|max:255',
            'cnname' => 'sometimes|nullable|string|max:255',
            'descr' => 'required|string|min:1',
            'type' => 'required|integer|min:1|exists:categories,id',
            'uplver' => 'sometimes|in:yes',
            'offer' => 'sometimes|nullable|integer|min:0',
            'price' => 'sometimes|nullable|integer|min:0',
            'pos_state' => 'sometimes|nullable|string|max:20',
            'pos_state_until' => 'sometimes|nullable|date',
            'tags' => 'sometimes|array',
            'custom_fields' => 'sometimes|array',
            'cover' => 'sometimes|nullable|string|max:500',
            'technical_info' => 'sometimes|nullable|string|max:30000',
            'file' => 'required|file|mimetypes:application/x-bittorrent,application/octet-stream,application/x-torrent|max:'.$this->maxTorrentKb(),
        ];
    }

    /**
     * Same byte limit as UploadFileService::getTorrentFile(), converted to
     * the kilobytes Laravel's `max` rule expects.
     */
    private function maxTorrentKb(): int
    {
        return max(1, intdiv(SiteConfig::current()->main->maxTorrentSize(), 1024));
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'descr.required' => Locale::trans('upload.blank_description', [], null),
            'descr.min' => Locale::trans('upload.blank_description', [], null),
            'type.required' => Locale::trans('upload.category_unselected', [], null),
            'type.min' => Locale::trans('upload.category_unselected', [], null),
            'type.integer' => Locale::trans('upload.invalid_category', [], null),
            'type.exists' => Locale::trans('upload.invalid_category', [], null),
            'file.required' => Locale::trans('upload.missing_torrent_file', [], null),
            'file.file' => Locale::trans('upload.missing_torrent_file', [], null),
            'file.uploaded' => Locale::trans('upload.missing_torrent_file', [], null),
            'file.mimetypes' => Locale::trans('upload.not_bencoded_file', [], null),
            'file.max' => Locale::trans('upload.torrent_file_too_big', [], null)
                .number_format(SiteConfig::current()->main->maxTorrentSize())
                .Locale::trans('upload.remake_torrent_note', [], null),
        ];
    }
}
