<!DOCTYPE html>
<html data-theme="{{ $theme }}" data-fontsize="{{ $fontSize }}"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="stylesheet" href="{{ \App\Support\AssetAppender::versionedSrc(\App\Support\Style::cssUriWithContext().'theme.css') }}" type="text/css">
<link rel="stylesheet" href="{{ \App\Support\AssetAppender::versionedSrc('css/modern.css') }}" type="text/css">
<link rel="stylesheet" href="{{ \App\Support\AssetAppender::versionedSrc('css/nxt.css') }}" type="text/css">
<style nonce="{{ $cspNonce ?? '' }}">
    body { margin: 8px; font-size: 13px; }
    .form-comments { display: flex; gap: 12px; }
    .form-comments .form { flex-basis: 60%; }
    .form-comments .comments { flex-basis: 40%; padding: 8px; }
    .form-comments .comments .comment {
        padding: 5px 10px;
        border: 1px solid var(--nxm-border, #ccc);
        border-radius: 10px;
        cursor: pointer;
        display: inline-block;
        margin: 4px 0;
    }
    .approval-form .row { margin-bottom: 10px; }
    .approval-form label.main { display: block; font-weight: bold; margin-bottom: 4px; }
    .approval-form label.radio { margin-right: 12px; font-weight: normal; }
    .approval-form textarea { width: 100%; box-sizing: border-box; }
    #approval-msg { min-height: 1.2em; margin: 6px 0; }
</style>
</head>
<body>
    <div class="form-comments">
        <form class="approval-form form" id="approval-form" action="/web/torrent-approval" method="post">
            @csrf
            <input type="hidden" name="torrent_id" value="{{ $torrent->id }}">
            <div class="row">
                <label class="main">{{ __('torrent.approval.status_label') }}</label>
                <div>
                    @foreach (\App\Models\Torrent::listApprovalStatus(true) as $status => $text)
                    <label class="radio"><input type="radio" name="approval_status" value="{{ $status }}" @if($status == $torrent->approval_status) checked @endif> {{ $text }}</label>
                    @endforeach
                </div>
            </div>
            <div class="row">
                <label class="main" for="approval-comment">{{ __('torrent.approval.comment_label') }}</label>
                <div>
                    <textarea id="approval-comment" name="comment" rows="4"></textarea>
                </div>
            </div>
            <div id="approval-msg" role="alert"></div>
            <div class="row">
                <button type="submit" class="nx-btn nx-btn--primary">{{ __('label.submit') }}</button>
                <button type="reset" class="nx-btn">{{ __('label.reset') }}</button>
            </div>
        </form>
        <div class="comments">
            @foreach($denyReasons as $reason)
            <span class="comment">{{ $reason->name }}</span>
            @endforeach
        </div>
    </div>
    <div>{{ __('torrent.approval.logs_label') }}</div>
    <x-data-table :caption="__('torrent.approval.logs_label')" captionHidden>
        <x-slot:head>
            <thead><tr>
                <th scope="col">ID</th>
                <th scope="col">{{ __('label.username') }}</th>
                <th scope="col">{{ __('label.action') }}</th>
                <th scope="col">{{ __('label.comment') }}</th>
                <th scope="col">{{ __('label.created_at') }}</th>
            </tr></thead>
        </x-slot:head>
        @foreach ($logs as $log)
        <tr>
            <td>{{ $log->id }}</td>
            <td>{{ $log->user?->username ?? '' }}</td>
            <td>{{ $log->actionTypeText }}</td>
            <td>{{ $log->comment }}</td>
            <td>{{ \App\Support\Time::formatDateTime($log->created_at) }}</td>
        </tr>
        @endforeach
    </x-data-table>
    {{ $logs->links() }}
<script nonce="{{ $cspNonce ?? '' }}">
    var approvalComment = document.getElementById('approval-comment');
    document.querySelector('.comments').addEventListener('click', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('comment')) {
            approvalComment.value += e.target.textContent;
        }
    });
    document.getElementById('approval-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var msg = document.getElementById('approval-msg');
        fetch(this.action, {
            method: 'POST',
            body: new FormData(this),
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) { return res.json(); }).then(function (response) {
            if (response.ret != 0) {
                msg.textContent = response.msg;
                return;
            }
            parent.window.location.reload();
        }).catch(function () {
            msg.textContent = 'Request failed';
        });
    });
</script>
</body>
</html>
