<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\TokenRepository;
use App\Repositories\UsercpRepository;
use App\Support\AssetAppender;
use App\Support\Globals;
use App\Support\Locale;

/**
 * Builds the API-token section (table, modal form, handlers JS) of the
 * usercp home dashboard. Split out of UsercpPageService to keep the page
 * service under the RepositorySizeTest baseline.
 */
final class UsercpTokenSectionBuilder
{
    public function __construct(
        private readonly Globals $globals,
        private readonly UsercpRepository $usercpRepository,
        private readonly TokenRepository $tokenRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    public function build(array $lang, User $userInfo): array
    {
        $langFunctions = (array) ($this->globals->get('lang_functions') ?? []);

        $permissions = $this->tokenRepository->listUserTokenPermissionAllowed();
        $permissionOptions = [];
        foreach ($permissions as $name => $permLabel) {
            $permissionOptions[] = sprintf('<label><input type="checkbox" name="permissions[]" value="%s">%s</label>', $name, $permLabel);
        }

        $tokens = $this->usercpRepository->getUserTokens($userInfo);

        $label = Locale::trans('token.label', [], null);
        $columnName = Locale::trans('label.name', [], null);
        $columnPermission = Locale::trans('token.permission', [], null);
        $columnCreatedAt = Locale::trans('label.created_at', [], null);
        $actionLabel = Locale::trans('label.action', [], null);
        $actionCreate = Locale::trans('label.create', [], null);
        $deleteLabel = $langFunctions['text_delete'] ?? 'Delete';
        $confirmRemoveLabel = $langFunctions['std_confirm_remove'] ?? 'Confirm remove?';

        $tableHtml = '';
        if (! empty($tokens)) {
            $tableHtml .= "<table border='1' cellspacing='0' cellpadding='5' id='token-table'><tr><td class='colhead'>ID</td><td class='colhead'>{$columnName}</td><td class='colhead'>{$columnPermission}</td><td class='colhead'>{$columnCreatedAt}</td><td class='colhead'>{$actionLabel}</td></tr>";
            foreach ($tokens as $tokenRecord) {
                $tableHtml .= '<tr>';
                $tableHtml .= sprintf('<td>%s</td>', (int) $tokenRecord['id']);
                $tableHtml .= sprintf('<td>%s</td>', htmlspecialchars((string) $tokenRecord['name']));
                $tableHtml .= sprintf('<td>%s</td>', htmlspecialchars((string) $tokenRecord['abilitiesText']));
                $tableHtml .= sprintf('<td>%s</td>', htmlspecialchars((string) $tokenRecord['created_at']));
                $tableHtml .= sprintf('<td><img style="cursor: pointer" class="staff_delete token-del" src="pic/trans.gif" alt="D" title="%s" data-id="%s"></td>', htmlspecialchars($deleteLabel), (int) $tokenRecord['id']);
                $tableHtml .= '</tr>';
            }
            $tableHtml .= '</table>';
        }
        $tableHtml .= sprintf('<div><input type="button" id="add-token-box-btn" value="%s"/></div>', htmlspecialchars($actionCreate));

        $permissionCheckbox = implode('', $permissionOptions);
        $tokenForm = <<<FORM
<div class="form-box">
<form id="token-box-form">
    <div class="form-control-row">
        <div class="label">{$columnName}</div>
        <div class="field"><input type="text" name="name"></div>
    </div>
    <div class="form-control-row">
        <div class="label">{$columnPermission}</div>
        <div class="field">{$permissionCheckbox}</div>
    </div>
</form>
</div>
FORM;

        $tokLabel = addslashes($label);
        $tokCreate = addslashes($actionCreate);
        $tokConfirmRemove = addslashes($confirmRemoveLabel);
        $tokenJs = <<<JS
document.getElementById('add-token-box-btn').addEventListener('click', function () {
    layer.open({
        type: 1,
        title: "{$tokLabel} {$tokCreate}",
        content: `{$tokenForm}`,
        btn: ['OK'],
        btnAlign: 'c',
        yes: function (index) {
            layer.close(index);
            var form = document.getElementById('token-box-form');
            var params = serializeForm(form);
            nativePost('/web/token/add', params, function (response) {
                console.log(response)
                if (response.ret != 0) {
                    layer.alert(response.msg, window.nexusLayerOptions.alert)
                } else {
                    layer.alert(response.msg, window.nexusLayerOptions.alert, function(index) {
                        layer.close(index);
                        window.location.reload()
                    })
                }
            })
        }
    })
});
var tokenTableEl = document.getElementById('token-table');
if (tokenTableEl) {
    tokenTableEl.addEventListener('click', function (e) {
        if (!e.target || !e.target.classList || !e.target.classList.contains('token-del')) return;
        var params = {id: e.target.getAttribute("data-id")}
        layer.confirm("{$tokConfirmRemove}", window.nexusLayerOptions.confirm, function (index) {
            layer.close(index)
            nativePost('/web/token/del', params, function (response) {
                console.log(response)
                if (response.ret != 0) {
                    layer.alert(response.msg, window.nexusLayerOptions.alert)
                    return
                }
                window.location.reload()
            })
        })
    });
}
JS;
        AssetAppender::js($tokenJs, 'footer', false);

        return [
            'label' => $label,
            'columnName' => $columnName,
            'columnPermission' => $columnPermission,
            'columnCreatedAt' => $columnCreatedAt,
            'actionLabel' => $actionLabel,
            'actionCreate' => $actionCreate,
            'permissionCheckbox' => $permissionCheckbox,
            'tokens' => $tokens,
            'deleteLabel' => $deleteLabel,
            'confirmRemoveLabel' => $confirmRemoveLabel,
            'tableHtml' => $tableHtml,
        ];
    }
}
