<p>{{ __('legacy/offers.text_red_star_required') }}<span class="text-nxm-danger">*</span>{{ __('legacy/offers.text_red_star_required_end') }}</p>
<div class="text-center"><form id="compose" action="/web/offers/create" name="compose" method="post">
@csrf
<div class="nx-fgrid nx-fgrid--flat">
<div class="nx-ffull nx-colhead text-center">{{ __('legacy/offers.text_offers_open_to_all')}}</div>
<div class="nx-fhead"><b>{{ __('legacy/offers.row_type')}}<span class="text-nxm-danger">*</span></b></div><div class="nx-fcell"> <select name=type>
<option value=0>{{ __('legacy/offers.select_type_select') }}</option>
@foreach ($add_offer['typeOptions'] ?? [] as $opt)<option value={{ $opt->id }}>{{ $opt->name }}</option>
@endforeach</select>
</div>
<div class="nx-fhead"><b>{{ __('legacy/offers.row_title')}}<span class="text-nxm-danger">*</span></b></div><div class="nx-fcell"><input type=text name=name /></div>
<div class="nx-fhead"><b>{{ __('legacy/offers.row_post_or_photo')}}</b></div><div class="nx-fcell"><input type=text name=picture><br />{{ __('legacy/offers.text_link_to_picture') }} <a href="/web/tags" title="What is Tag?">{{ __('legacy/offers.text_tag') }}</a> {{ __('legacy/offers.text_link_to_picture_end') }}</div>
<div class="nx-fhead"><b>{{ __('legacy/offers.row_description')}}<b><span class="text-nxm-danger">*</span></div><div class="nx-fcell">
<livewire:bbcode-editor form="compose" text="body" :content="$add_offer['bodyContent'] ?? ''" />
</div>
<div class="nx-ffull nx-toolbox text-center"><input id=qr type=submit class=btn value={{ __('legacy/offers.submit_add_offer')}} ></div>
</div></form><br />
