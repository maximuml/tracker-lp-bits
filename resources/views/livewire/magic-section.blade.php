<div>
    @if ($vm->disabledValue !== null)
        <input class="nx-postbtn" type="button" value="{{ $vm->disabledValue }}" disabled="disabled" />
    @else
        <ul id="listNumber" class="magic">
            @foreach ($vm->options as $option)
                <li wire:click="give({{ $option }})"><span>+{{ $option }}</span></li>
            @endforeach
        </ul>
    @endif
    &nbsp;{{ $vm->haveGotBonusPre }}<span id="spanSumAll">{{ $vm->sumValue }}</span>{{ $vm->haveGotBonusPost }}&nbsp;@if ($vm->hiddenGivers !== [] && ! $showAll)<a href="#" wire:click.prevent="showAllGivers">[{{ $vm->showAllText }}]</a><br/>@endif
    <div><span id='current_user_magic' class='nx-hidden'>{{ $vm->currentUser }}</span>&nbsp;@if ($vm->hasGivers())@foreach ($vm->visibleGivers as $giver){{ $giver }}   @endforeach @if ($vm->hiddenGivers !== [] && ! $showAll)<span id="ellipsis">&nbsp;......&nbsp;</span>@endif @if ($showAll)@foreach ($vm->hiddenGivers as $giver){{ $giver }}   @endforeach @endif(<span id="magic_newest_record">{{ $vm->newestRecordText }}</span>{{ $vm->sumGivePre }}<span id="count_user_spa">{{ $vm->countUserNumber }}</span>{{ $vm->sumGivePost }})@endif</div>
    @if ($status !== '')
        <div class="nx-field__error" role="alert">{{ $status }}</div>
    @endif
</div>
