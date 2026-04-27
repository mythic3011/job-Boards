{{--
    Honeypot protection fields.
    - A hidden "website" field that bots fill but humans leave empty
    - A timing token to detect instant (bot) submissions
    Include inside any <form> tag after @csrf.
--}}
@php($honeypotFieldName = config('honeypot.field_name', 'website'))
<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;" tabindex="-1">
    <label for="{{ $honeypotFieldName }}">Website</label>
    <input
        type="text"
        id="{{ $honeypotFieldName }}"
        name="{{ $honeypotFieldName }}"
        value=""
        autocomplete="off"
        data-bwignore="true"
        data-1p-ignore="true"
        data-lpignore="true"
        tabindex="-1"
    >
</div>
<input type="hidden" name="_timing" value="{{ encrypt(time()) }}" data-bwignore="true" data-1p-ignore="true" data-lpignore="true">
