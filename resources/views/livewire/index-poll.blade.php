<section class="nx-idx-card">
<h2>{{ $polls->title }}
    @if($polls->canManage)
        <span class="small"> - [<a class="altlink" href="/web/makepoll?returnto=main"><b>{{ $polls->newLabel }}</b></a>]
        @if($polls->exists)
             - [<a class="altlink" href="/web/makepoll?action=edit&amp;pollid={{ $polls->pollId }}&amp;returnto=main"><b>{{ $polls->editLabel }}</b></a>]
             - [<a class="altlink" href="/web/log?action=poll&amp;do=delete&amp;pollid={{ $polls->pollId }}&amp;returnto=main"><b>{{ $polls->deleteLabel }}</b></a>]
             - [<a class="altlink" href="/web/polloverview?id={{ $polls->pollId }}"><b>{{ $polls->detailLabel }}</b></a>]
        @endif
        </span>
    @endif
</h2>
@if(! $polls->exists)
<x-empty-state :title="__('index.std_no_poll')" />
@endif
@if($polls->exists)
<div class="p-[10pt] text-center">
<div class="nx-main nx-box nx-box--59">
<p class="text-center"><b>{{ $polls->question }}</b></p>
@if($polls->hasVoted)
    <div class="nx-main">
    @foreach($polls->bars as $bar)
        <div class="flex items-start"><div class="nx-embedded whitespace-nowrap">{{ $bar->option }}&nbsp;&nbsp;</div><div class="nx-embedded whitespace-nowrap grow"><img class="bar_end" src="pic/trans.gif" alt="" /><img class="{{ $bar->selected ? 'sltbar' : 'unsltbar' }}" src="pic/trans.gif" alt="" /><img class="bar_end" src="pic/trans.gif" alt="" /> {{ $bar->percent }}%</div></div>
    @endforeach
    </div>
    <p class="text-center">{{ $polls->votesLabel }} {{ $polls->totalVotes }}</p>
    @if($polls->canLog)
        <p class="text-center"><a href="/web/log?action=poll">{{ $polls->previousPollsLabel }}</a></p>
    @endif
@else
    <form wire:submit="vote">
    @foreach($polls->options as $i => $option)
        <label><input type="radio" name="choice" value="{{ $i }}" wire:model.number="choice">{{ $option }}</label><br />
    @endforeach
    <br />
    <label><input type="radio" name="choice" value="255" wire:model.number="choice">{{ $polls->blankVoteLabel }}</label><br />
    <p class="text-center"><input type="submit" class="btn" value="{{ $polls->submitVoteLabel }}" /></p>
    </form>
@endif
</div>
</div>
@endif
</section>
