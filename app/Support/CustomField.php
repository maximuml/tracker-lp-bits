<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SearchBox;
use App\Models\TorrentCustomField;
use App\Models\TorrentCustomFieldValue;
use App\Support\Html\SafeHtml;
use Illuminate\Database\Eloquent\Collection;

class CustomField
{
    const TYPE_TEXT = 0;

    const TYPE_TEXTAREA = 1;

    const TYPE_RADIO = 3;

    const TYPE_CHECKBOX = 4;

    const TYPE_SELECT = 2;

    const TYPE_IMAGE = 5;

    /** @var array<int, array{text: string, has_option: bool, is_value_multiple: bool}> */
    public static array $types = [
        self::TYPE_TEXT => [
            'text' => 'text',
            'has_option' => false,
            'is_value_multiple' => false,
        ],
        self::TYPE_TEXTAREA => [
            'text' => 'textarea',
            'has_option' => false,
            'is_value_multiple' => false,
        ],
        self::TYPE_RADIO => [
            'text' => 'radio',
            'has_option' => true,
            'is_value_multiple' => false,
        ],
        self::TYPE_CHECKBOX => [
            'text' => 'checkbox',
            'has_option' => true,
            'is_value_multiple' => true,
        ],
        self::TYPE_SELECT => [
            'text' => 'select',
            'has_option' => true,
            'is_value_multiple' => false,
        ],
        self::TYPE_IMAGE => [
            'text' => 'image',
            'has_option' => false,
            'is_value_multiple' => false,
        ],
    ];

    public function getTypeHuman(int $type): string
    {
        $map = [
            self::TYPE_TEXT => Locale::trans('field.type.text', [], null),
            self::TYPE_TEXTAREA => Locale::trans('field.type.textarea', [], null),
            self::TYPE_RADIO => Locale::trans('field.type.radio', [], null),
            self::TYPE_CHECKBOX => Locale::trans('field.type.checkbox', [], null),
            self::TYPE_SELECT => Locale::trans('field.type.select', [], null),
            self::TYPE_IMAGE => Locale::trans('field.type.image', [], null),
        ];

        return $map[$type] ?? '';
    }

    /** @return array<int, string> */
    public function getTypeRadioOptions(): array
    {
        $out = [];
        foreach (self::$types as $key => $value) {
            $out[$key] = sprintf('%s(%s)', $value['text'], $this->getTypeHuman((int) $key));
        }

        return $out;
    }

    /**
     * @param  array<int|string, mixed>  $overrideValues  field-id => submitted
     *                                                    value, used to restore user input after a failed upload POST
     */
    public function renderOnUploadPage(int $torrentId, int $searchBoxId, array $overrideValues = []): string
    {
        $searchBox = SearchBox::query()->find($searchBoxId);
        if (empty($searchBox)) {
            throw new \RuntimeException("Invalid search box: $searchBoxId");
        }
        $customValues = $this->listTorrentCustomField($torrentId, $searchBoxId);
        $customFieldsRaw = $searchBox->custom_fields;
        $customFieldIds = array_filter(array_map('intval', is_array($customFieldsRaw) ? $customFieldsRaw : explode(',', (string) ($customFieldsRaw ?? ''))));
        $res = TorrentCustomField::query()
            ->whereIn('id', $customFieldIds)
            ->orderBy('priority', 'desc')
            ->toBase()
            ->get();
        $cspNonce = (string) request()->attributes->get('csp_nonce', '');
        $baseUrl = Url::schemeAndHost(false);
        $html = '';
        foreach ($res as $row) {
            $row = (array) $row;
            $type = (int) $row['type'];
            $name = "custom_fields[$searchBoxId][{$row['id']}]";
            $currentValue = $overrideValues[$row['id']] ?? $customValues[$row['id']]['custom_field_value'] ?? '';
            if (is_array($currentValue) && ! in_array($type, [self::TYPE_CHECKBOX, self::TYPE_SELECT], true)) {
                $currentValue = '';
            }
            if ($type === self::TYPE_CHECKBOX) {
                $name .= '[]';
            }

            $options = [];
            if ($type === self::TYPE_RADIO || $type === self::TYPE_CHECKBOX || $type === self::TYPE_SELECT) {
                foreach (preg_split('/[\r\n]+/', trim((string) $row['options'])) ?: [] as $option) {
                    if (empty($option) || ($pos = strpos($option, '|')) === false) {
                        continue;
                    }
                    $value = substr($option, 0, $pos);
                    $options[] = [
                        'value' => $value,
                        'label' => substr($option, $pos + 1),
                        'checked' => $type === self::TYPE_RADIO
                            ? (string) $currentValue === $value
                            : in_array($value, (array) $currentValue),
                    ];
                }
            }

            $params = [
                'relation' => "mode_$searchBoxId",
                'label' => (string) $row['label'],
                'required' => (bool) $row['required'],
                'type' => $type,
                'name' => $name,
                'value' => $currentValue,
                'options' => $options,
            ];

            if ($type === self::TYPE_IMAGE) {
                $callbackFunc = 'preview_custom_field_image_'.$row['id'];
                $previewHtml = '';
                if (! empty($currentValue)) {
                    $previewHtml = substr((string) $currentValue, 0, 4) === 'http'
                        ? Html::formatImg((string) $currentValue, true, 700, 0, 'attach'.$row['id'])
                        : (string) Format::formatComment((string) $currentValue);
                }
                $params += [
                    'callbackFunc' => $callbackFunc,
                    'iframeId' => "iframe_$callbackFunc",
                    'inputId' => "input_$callbackFunc",
                    'imgId' => 'attach'.$row['id'],
                    'previewBoxId' => "preview_$callbackFunc",
                    'previewHtml' => SafeHtml::fromTrustedHtml($previewHtml),
                    'baseUrl' => $baseUrl,
                    'cspNonce' => $cspNonce,
                ];
            }

            $html .= view('fields._upload_field', $params)->render();
        }

        return $html;
    }

    /**
     * @param  int|array<int, int>  $torrentId
     * @return array<int|string, array<int|string, mixed>>
     */
    public function listTorrentCustomField(int|array $torrentId, int $searchBoxId): array
    {
        // suppose torrentId is array
        $isArray = is_array($torrentId);
        $torrentIdArr = is_array($torrentId) ? $torrentId : [$torrentId];
        $searchBox = SearchBox::query()->find($searchBoxId);
        if (empty($searchBox)) {
            throw new \RuntimeException("Invalid search box: $searchBoxId");
        }
        $customFieldsRaw = $searchBox->custom_fields;
        $customFieldIds = array_filter(array_map('intval', is_array($customFieldsRaw) ? $customFieldsRaw : explode(',', (string) ($customFieldsRaw ?? ''))));
        if (empty($customFieldIds)) {
            return [];
        }
        $torrentIdArr = array_map('intval', $torrentIdArr);

        $res = TorrentCustomFieldValue::query()
            ->from('torrents_custom_field_values as v')
            ->join('torrents_custom_fields as f', 'v.custom_field_id', '=', 'f.id')
            ->whereIn('v.torrent_id', $torrentIdArr)
            ->whereIn('f.id', $customFieldIds)
            ->orderBy('f.priority', 'desc')
            ->select('f.*', 'v.custom_field_value', 'v.torrent_id')
            ->toBase()
            ->get();
        $values = [];
        $result = [];
        foreach ($res as $row) {
            $row = (array) $row;
            $typeInfo = self::$types[$row['type']];
            if ($typeInfo['has_option']) {
                $options = preg_split('/[\r\n]+/', trim((string) $row['options'])) ?: [];
                $optionsArr = [];
                foreach ($options as $option) {
                    $pos = strpos($option, '|');
                    if ($pos === false) {
                        continue;
                    }
                    $value = substr($option, 0, $pos);
                    $label = substr($option, $pos + 1);
                    $optionsArr[$value] = $label;
                }
                $row['options'] = $optionsArr;
            }
            $result[$row['torrent_id']][$row['id']] = $row;
            if ($typeInfo['is_value_multiple']) {
                $values[$row['torrent_id']][$row['id']] = json_decode((string) $row['custom_field_value'], true);
            } else {
                $values[$row['torrent_id']][$row['id']] = $row['custom_field_value'];
            }
        }
        foreach ($result as $tid => &$fields) {
            foreach ($fields as &$field) {
                $field['custom_field_value'] = $values[$tid][$field['id']];
            }
        }

        return $isArray ? $result : ($result[$torrentId] ?? []);
    }

    public function renderOnTorrentDetailsPage(int $torrentId, int $searchBoxId): string
    {
        $displayName = \App\Support\SearchBox::valueWithContext($searchBoxId, 'custom_fields_display_name');
        $customFields = $this->listTorrentCustomField($torrentId, $searchBoxId);
        $mixedRowContent = \App\Support\SearchBox::valueWithContext($searchBoxId, 'custom_fields_display');
        $rowByRowHtml = '';
        $shouldRenderMixRow = false;
        foreach ($customFields as $field) {
            if (empty($field['custom_field_value'])) {
                // No value, remove special tags
                $mixedRowContent = str_replace("<%{$field['name']}.label%>", '', $mixedRowContent);
                $mixedRowContent = str_replace("<%{$field['name']}.value%>", '', $mixedRowContent);

                continue;
            }
            $shouldRenderMixRow = true;
            $contentNotFormatted = $this->formatCustomFieldValue($field, false);
            $mixedRowContent = str_replace("<%{$field['name']}.label%>", $field['label'], $mixedRowContent);
            $mixedRowContent = str_replace("<%{$field['name']}.value%>", $contentNotFormatted, $mixedRowContent);
            if ($field['is_single_row']) {
                if (! empty($field['display'])) {
                    $customFieldDisplay = (string) $field['display'];
                    $customFieldDisplay = str_replace("<%{$field['name']}.label%>", $field['label'], $customFieldDisplay);
                    $customFieldDisplay = str_replace("<%{$field['name']}.value%>", $contentNotFormatted, $customFieldDisplay);
                    $rowByRowHtml .= Html::tr($field['label'], Format::formatComment($customFieldDisplay), 1, '', true);
                } else {
                    $contentFormatted = $this->formatCustomFieldValue($field, true);
                    $rowByRowHtml .= Html::tr($field['label'], $contentFormatted, 1, '', true);
                }
            }
        }

        $result = $rowByRowHtml;
        if ($shouldRenderMixRow && $mixedRowContent) {
            $result .= Html::tr($displayName, Format::formatComment((string) $mixedRowContent), 1, '', true);
        }

        return $result;
    }

    /** @param  array<int|string, mixed>  $customFieldWithValue */
    protected function formatCustomFieldValue(array $customFieldWithValue, bool $doFormatComment = false): string
    {
        $result = '';
        $fieldValue = $customFieldWithValue['custom_field_value'];
        switch ($customFieldWithValue['type']) {
            case self::TYPE_TEXT:
            case self::TYPE_TEXTAREA:
                $result .= $doFormatComment ? Format::formatComment((string) $fieldValue) : (string) $fieldValue;
                break;
            case self::TYPE_IMAGE:
                if (substr((string) $fieldValue, 0, 4) == 'http') {
                    $result .= $doFormatComment ? Html::formatImg((string) $fieldValue, true, 700, 0, "attach{$customFieldWithValue['id']}") : (string) $fieldValue;
                } else {
                    $result .= $doFormatComment ? Format::formatComment((string) $fieldValue) : (string) $fieldValue;
                }
                break;
            case self::TYPE_RADIO:
            case self::TYPE_CHECKBOX:
            case self::TYPE_SELECT:
                $fieldContent = [];
                foreach ((array) $fieldValue as $item) {
                    $fieldContent[] = $customFieldWithValue['options'][$item] ?? '';
                }
                $result .= implode(' ', $fieldContent);
                break;
            default:
                break;
        }

        return $result;
    }

    /** @param  array<int|string, mixed>  $data */
    public function saveFieldValues(int $searchBoxId, int $torrentId, array $data): void
    {
        $searchBox = SearchBox::query()->findOrFail($searchBoxId);
        $enabledFields = TorrentCustomField::query()->find($searchBox->custom_fields);
        $insert = [];
        $now = now();
        if ($enabledFields instanceof Collection) {
            foreach ($enabledFields as $field) {
                if (empty($data[$field->id])) {
                    if ($field->required) {
                        //                    throw new \InvalidArgumentException(nexus_trans("nexus.require_argument", ['argument' => $field->label]));
                        Logger::writeWithContext((string) "Field: {$field->label} required, but empty", (string) 'info', (bool) false);
                    }

                    continue;
                }
                $insert[] = [
                    'torrent_id' => $torrentId,
                    'custom_field_id' => $field->id,
                    'custom_field_value' => is_array($data[$field->id]) ? json_encode($data[$field->id]) : $data[$field->id],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        TorrentCustomFieldValue::query()->where('torrent_id', $torrentId)->delete();
        TorrentCustomFieldValue::query()->insert($insert);
    }
}
