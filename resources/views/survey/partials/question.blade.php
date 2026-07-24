@php $name = $question->fieldName(); $val = old($name); $cfg = $question->config ?? []; @endphp

@if($question->isSection())
    <div class="mt-7 pb-1.5 border-b-2" style="border-color:var(--color-accent)">
        <h3 class="text-lg font-extrabold uppercase tracking-wide m-0" style="color:var(--color-accent)">{{ $question->label }}</h3>
    </div>
@else
<div class="py-5 border-b border-[color:var(--color-line)] last:border-b-0" data-type="{{ $question->type }}">
    <label class="block font-bold text-lg mb-1" @if(!$question->isChoice() && $question->type!=='scale') for="{{ $name }}" @endif>
        {{ $question->label }}@if($question->is_required)<span style="color:var(--color-accent)" class="ml-1">*</span>@endif
    </label>
    @if($question->help_text)<p class="text-sm text-[color:var(--color-muted)] mb-3">{{ $question->help_text }}</p>@endif

    @switch($question->type)
        @case('textarea')
            <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $cfg['rows'] ?? 4 }}" class="field"
                @if(isset($cfg['maxlength'])) maxlength="{{ $cfg['maxlength'] }}" @endif
                placeholder="{{ $question->placeholder }}">{{ $val }}</textarea>
            @break

        @case('select')
            <select id="{{ $name }}" name="{{ $name }}" class="field">
                <option value="">— izberi —</option>
                @foreach($question->options ?? [] as $o)
                    <option value="{{ $o['value'] }}" @selected($val==$o['value'])>{{ $o['label'] }}</option>
                @endforeach
            </select>
            @break

        @case('radio')
            @foreach($question->options ?? [] as $o)
                <label class="opt {{ $val==$o['value'] ? 'checked' : '' }}">
                    <input type="radio" name="{{ $name }}" value="{{ $o['value'] }}"
                        @checked($val==$o['value'])
                        @if($o['value']==='drugo') data-other-toggle="{{ $name }}_other" @endif>
                    <span>{{ $o['label'] }}</span>
                </label>
            @endforeach
            @if(collect($question->options ?? [])->contains('value', 'drugo'))
                <input type="text" name="{{ $name }}_other" id="{{ $name }}_other" class="field mt-2"
                    placeholder="Prosimo, navedite..." value="{{ old($name.'_other') }}"
                    style="{{ $val === 'drugo' ? '' : 'display:none' }}">
                @error($name.'_other')<div class="text-sm mt-1.5" style="color:#ff6b6b">{{ $message }}</div>@enderror
            @endif
            @break

        @case('checkbox')
            @php $vals = is_array($val) ? $val : []; @endphp
            @foreach($question->options ?? [] as $o)
                <label class="opt {{ in_array($o['value'],$vals) ? 'checked' : '' }}">
                    <input type="checkbox" name="{{ $name }}[]" value="{{ $o['value'] }}"
                        @checked(in_array($o['value'],$vals))
                        @if($o['value']==='drugo') data-other-toggle="{{ $name }}_other" @endif>
                    <span>{{ $o['label'] }}</span>
                </label>
            @endforeach
            @if(collect($question->options ?? [])->contains('value', 'drugo'))
                <input type="text" name="{{ $name }}_other" id="{{ $name }}_other" class="field mt-2"
                    placeholder="Prosimo, navedite..." value="{{ old($name.'_other') }}"
                    style="{{ in_array('drugo',$vals) ? '' : 'display:none' }}">
                @error($name.'_other')<div class="text-sm mt-1.5" style="color:#ff6b6b">{{ $message }}</div>@enderror
            @endif
            @break

        @case('scale')
            <div class="scale">
                @for($i = ($cfg['min'] ?? 1); $i <= ($cfg['max'] ?? 5); $i += ($cfg['step'] ?? 1))
                    <label class="{{ (string)$val===(string)$i ? 'checked' : '' }}">
                        <input type="radio" name="{{ $name }}" value="{{ $i }}" @checked((string)$val===(string)$i)><span>{{ $i }}</span>
                    </label>
                @endfor
            </div>
            @break

        @case('boolean')
            <div class="scale max-w-60">
                <label class="{{ (string)$val==='1' ? 'checked' : '' }}"><input type="radio" name="{{ $name }}" value="1" @checked((string)$val==='1')><span>Da</span></label>
                <label class="{{ (string)$val==='0' ? 'checked' : '' }}"><input type="radio" name="{{ $name }}" value="0" @checked((string)$val==='0')><span>Ne</span></label>
            </div>
            @break

        @case('number')
        @case('email')
        @case('tel')
        @case('date')
            <input type="{{ $question->type }}" id="{{ $name }}" name="{{ $name }}" value="{{ $val }}" class="field"
                placeholder="{{ $question->placeholder }}"
                @isset($cfg['min']) min="{{ $cfg['min'] }}" @endisset
                @isset($cfg['max']) max="{{ $cfg['max'] }}" @endisset
                @isset($cfg['step']) step="{{ $cfg['step'] }}" @endisset>
            @break

        @default
            <input type="text" id="{{ $name }}" name="{{ $name }}" value="{{ $val }}" class="field"
                placeholder="{{ $question->placeholder }}"
                @isset($cfg['maxlength']) maxlength="{{ $cfg['maxlength'] }}" @endisset>
    @endswitch

    @error($name)<div class="text-sm mt-1.5" style="color:#ff6b6b">{{ $message }}</div>@enderror
    @if($question->isMulti())@error($name.'.*')<div class="text-sm mt-1.5" style="color:#ff6b6b">{{ $message }}</div>@enderror @endif
</div>
@endif