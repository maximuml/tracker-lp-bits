@props(['rows' => [], 'caption' => '', 'lang' => []])
<x-topten.frame :caption="$caption">
<tr><td class="colhead">{{ $lang['col_rank'] ?? '' }}</td><td class="colhead">{{ $lang['col_username'] ?? '' }}</td><td class="colhead">{{ $lang['col_topics'] ?? '' }}</td><td class="colhead">{{ $lang['col_posts'] ?? '' }}</td></tr>
@foreach ($rows as $a)
<tr><td class="rowfollow" align="center">{{ $loop->iteration }}</td><td class="rowfollow" align="left"><x-topten.user-link :id="$a['userid'] ?? 0" /></td><td class="rowfollow" align="right">{{ number_format((int) ($a['usertopics'] ?? 0)) }}</td><td class="rowfollow" align="right">{{ number_format((int) ($a['userposts'] ?? 0)) }}</td></tr>
@endforeach
</x-topten.frame>
