{{-- Legacy BBCode editor: toolbar + textarea + smilies. Behaviour is in
     public/js/bbcode-editor.js (queued by the component class); buttons are
     dispatched by the delegated data-bbcode-action listener in common.js.
     Toolbar controls keep their name attributes — legacy code addresses them
     via document.forms[form][name]. --}}
<div class="bbcode-editor" data-bbcode-editor
     data-form="{{ $form }}" data-text="{{ $text }}"
     data-edit-id="{{ $editId }}" data-preview-id="{{ $previewId }}"
     data-btn-edit-id="{{ $btnEditId }}" data-btn-preview-id="{{ $btnPreviewId }}">
    <div id="{{ $editId }}" class="bbcode-edit">
        <div class="bbcode-toolbar">
            <input class="bbcode-btn bbcode-btn-b" type="button" name="b" value="B" data-bbcode-action="simpletag" data-bbcode-tag="b" />
            <input class="codebuttons bbcode-btn-i" type="button" name="i" value="I" data-bbcode-action="simpletag" data-bbcode-tag="i" />
            <input class="codebuttons bbcode-btn-u" type="button" name="u" value="U" data-bbcode-action="simpletag" data-bbcode-tag="u" />
            <input class="codebuttons" type="button" name="url" value="URL" data-bbcode-action="tag_url" data-prompt1="{{ $langFunctions['js_prompt_enter_url'] ?? '' }}" data-prompt2="{{ $langFunctions['js_prompt_enter_title'] ?? '' }}" data-prompt3="{{ $langFunctions['js_prompt_error'] ?? '' }}" />
            <input class="codebuttons" type="button" name="IMG" value="IMG" data-bbcode-action="tag_image" data-prompt1="{{ $langFunctions['js_prompt_enter_image_url'] ?? '' }}" data-prompt2="{{ $langFunctions['js_prompt_error'] ?? '' }}" />
            <input type="button" name="list" value="List" data-bbcode-action="tag_list" data-prompt1="{{ $langFunctions['js_prompt_enter_item'] ?? '' }}" data-prompt2="{{ $langFunctions['js_prompt_error'] ?? '' }}" />
            <input class="codebuttons" type="button" name="quote" value="QUOTE" data-bbcode-action="simpletag" data-bbcode-tag="quote" />
            <input type="button" name="tagcount" value="Close all tags" data-bbcode-action="closeall" />
            <select class="med codebuttons" name="color" data-bbcode-alterfont="color">
                <option value="0">--- {{ $langFunctions['select_color'] ?? '' }} ---</option>
                <option value="Black">Black</option>
                <option value="Sienna">Sienna</option>
                <option value="DarkOliveGreen">Dark Olive Green</option>
                <option value="DarkGreen">Dark Green</option>
                <option value="DarkSlateBlue">Dark Slate Blue</option>
                <option value="Navy">Navy</option>
                <option value="Indigo">Indigo</option>
                <option value="DarkSlateGray">Dark Slate Gray</option>
                <option value="DarkRed">Dark Red</option>
                <option value="DarkOrange">Dark Orange</option>
                <option value="Olive">Olive</option>
                <option value="Green">Green</option>
                <option value="Teal">Teal</option>
                <option value="Blue">Blue</option>
                <option value="SlateGray">Slate Gray</option>
                <option value="DimGray">Dim Gray</option>
                <option value="Red">Red</option>
                <option value="SandyBrown">Sandy Brown</option>
                <option value="YellowGreen">Yellow Green</option>
                <option value="SeaGreen">Sea Green</option>
                <option value="MediumTurquoise">Medium Turquoise</option>
                <option value="RoyalBlue">Royal Blue</option>
                <option value="Purple">Purple</option>
                <option value="Gray">Gray</option>
                <option value="Magenta">Magenta</option>
                <option value="Orange">Orange</option>
                <option value="Yellow">Yellow</option>
                <option value="Lime">Lime</option>
                <option value="Cyan">Cyan</option>
                <option value="DeepSkyBlue">Deep Sky Blue</option>
                <option value="DarkOrchid">Dark Orchid</option>
                <option value="Silver">Silver</option>
                <option value="Pink">Pink</option>
                <option value="Wheat">Wheat</option>
                <option value="LemonChiffon">Lemon Chiffon</option>
                <option value="PaleGreen">Pale Green</option>
                <option value="PaleTurquoise">Pale Turquoise</option>
                <option value="LightBlue">Light Blue</option>
                <option value="Plum">Plum</option>
                <option value="White">White</option>
            </select>
            <select class="med codebuttons" name="font" data-bbcode-alterfont="font">
                <option value="0">--- {{ $langFunctions['select_font'] ?? '' }} ---</option>
                <option value="Arial">Arial</option>
                <option value="Arial Black">Arial Black</option>
                <option value="Arial Narrow">Arial Narrow</option>
                <option value="Book Antiqua">Book Antiqua</option>
                <option value="Century Gothic">Century Gothic</option>
                <option value="Comic Sans MS">Comic Sans MS</option>
                <option value="Courier New">Courier New</option>
                <option value="Fixedsys">Fixedsys</option>
                <option value="Garamond">Garamond</option>
                <option value="Georgia">Georgia</option>
                <option value="Impact">Impact</option>
                <option value="Lucida Console">Lucida Console</option>
                <option value="Lucida Sans Unicode">Lucida Sans Unicode</option>
                <option value="Microsoft Sans Serif">Microsoft Sans Serif</option>
                <option value="Palatino Linotype">Palatino Linotype</option>
                <option value="System">System</option>
                <option value="Tahoma">Tahoma</option>
                <option value="Times New Roman">Times New Roman</option>
                <option value="Trebuchet MS">Trebuchet MS</option>
                <option value="Verdana">Verdana</option>
            </select>
            <select class="med codebuttons" name="size" data-bbcode-alterfont="size">
                <option value="0">--- {{ $langFunctions['select_size'] ?? '' }} ---</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
                <option value="5">5</option>
                <option value="6">6</option>
                <option value="7">7</option>
            </select>
        </div>
        @if ($enableAttach)
            <iframe src="{{ $attachUrl }}" class="bbcode-attach" frameborder="0" scrolling="no" marginheight="0" marginwidth="0"></iframe>
        @endif
        <div class="bbcode-body">
            <textarea class="bbcode" cols="100" name="{{ $text }}" id="{{ $text }}" rows="20" data-ctrlenter="compose:qr">{{ $content }}</textarea>
            <div class="bbcode-smilies-wrap">
                <div class="bbcode-smilies">
                    @foreach ($quickSmilies as $smily)
                        <span class="bbcode-smile">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Smilies::link($form, $text, (int) $smily)))</span>
                    @endforeach
                </div>
                <a href="#" data-bbcode-action="winop">{{ $langFunctions['text_more_smilies'] ?? '' }}</a>
            </div>
        </div>
    </div>
    @if ($withPreview)
        <div id="{{ $previewId }}" class="bbcode-preview"></div>
        <div class="bbcode-actions">
            <input id="{{ $btnPreviewId }}" type="button" class="btn" value="{{ $langFunctions['submit_preview'] ?? '' }}" data-bbcode-action="preview" />
            <input id="{{ $btnEditId }}" type="button" class="btn nx-hidden" value="{{ $langFunctions['submit_edit'] ?? '' }}" data-bbcode-action="edit" />
        </div>
    @endif
</div>
