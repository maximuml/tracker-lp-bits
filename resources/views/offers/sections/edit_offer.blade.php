<form id="compose" method="post" name="compose" action="?id={{ $edit_offer['id'] }}&amp;take_off_edit=1">
@csrf
<div class="nx-fgrid nx-fgrid--flat nx-w-97">
<div class="nx-ffull nx-colhead nx-center">{{ __('legacy/offers.text_edit_offer')}}</div>
<div class="nx-fhead">{{ __('legacy/offers.row_type')}}<font color="red">*</font></div><div class="nx-fcell">{{ $edit_offer['catSelect'] ?? '' }}</div>
<div class="nx-fhead">{{ __('legacy/offers.row_title')}}<font color="red">*</font></div><div class="nx-fcell"><input type="text" style="width: 99%" name="name" value="{{ $edit_offer['title'] }}" /></div>
<div class="nx-fhead">{{ __('legacy/offers.row_post_or_photo')}}</div><div class="nx-fcell"><input type="text" name="picture" style="width: 99%" value='' /><br />{{ __('legacy/offers.text_link_to_picture') }} <a href="tags.php" title="What is Tag?">{{ __('legacy/offers.text_tag') }}</a> {{ __('legacy/offers.text_link_to_picture_end') }}</div>
<div class="nx-fhead"><b>{{ __('legacy/offers.row_description')}}<font color="red">*</font></b></div><div class="nx-fcell">
{{ $edit_offer['bbcodeEditor'] ?? '' }}
</div>
<div class="nx-ffull nx-toolbox nx-center" style="vertical-align: middle; padding-top: 10px; padding-bottom: 10px;"><input id="qr" type="submit" value="{{ __('legacy/offers.submit_edit_offer')}}" class="btn" /></div>
</div></form><br />
