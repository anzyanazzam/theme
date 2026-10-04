@extends('layouts.admin')

@section('title')
    Theme
@endsection

@section('content-header')
    <h1>Theme<small>Logo, favicon and login page background.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Theme</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.nebula') }}" method="POST" enctype="multipart/form-data">
    {!! csrf_field() !!}
    <div class="row">
        <div class="col-xs-12 col-lg-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Logo &amp; favicon</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted small" style="margin-top:0">
                        The logo is shown in the top-left corner, before the panel name
                        ("{{ config('app.name', 'Pterodactyl') }}"), on the login page and in the admin area.
                        The browser tab icon (favicon) always follows the logo.
                    </p>

                    <div class="form-group">
                        <label class="control-label">Preview</label>
                        <div class="nb-logo-preview" id="nb-logo-preview">
                            @if($resolved['has_logo'])
                                <img id="nb-logo-img" src="{{ $resolved['logo_url'] }}" alt="Logo" style="height: {{ $resolved['logo_height'] }}px">
                            @else
                                <img id="nb-logo-img" src="" alt="Logo" style="height: {{ $resolved['logo_height'] }}px; display:none">
                            @endif
                            <span id="nb-logo-name" @if(!$resolved['show_name']) style="display:none" @endif>{{ config('app.name', 'Pterodactyl') }}</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="logo" class="control-label">Upload logo</label>
                        <input type="file" id="logo" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif">
                        <p class="help-block">PNG, JPG, WebP or GIF, up to 2&nbsp;MB. A square PNG or WebP with a transparent background works best (it is also used as the favicon).</p>
                    </div>

                    @if($resolved['has_logo'])
                        <div class="checkbox" style="margin-top:0">
                            <label><input type="checkbox" name="remove_logo" value="1"> Remove the current logo (use the default favicon)</label>
                        </div>
                    @endif

                    <div class="form-group">
                        <label for="logo_height" class="control-label">Logo height: <span class="nb-range-value" id="logo_height_val">{{ $resolved['logo_height'] }}</span>px</label>
                        <input type="range" id="logo_height" name="logo_height" min="20" max="64" step="1" value="{{ $resolved['logo_height'] }}" style="width:100%">
                    </div>

                    <div class="checkbox">
                        <label>
                            <input type="hidden" name="show_name" value="0">
                            <input type="checkbox" name="show_name" value="1" id="show_name" @if($resolved['show_name']) checked @endif>
                            Show the panel name next to the logo
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xs-12 col-lg-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Login page background</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label class="control-label">Preview</label>
                        <div class="nb-preview" id="nb-bg-preview">
                            <div class="nb-preview-img" id="nb-bg-img" style="@if($resolved['has_bg']) background-image:url('{{ $resolved['bg_url'] }}'); @else display:none; @endif filter: blur({{ $resolved['bg_blur'] }}px)"></div>
                            <div class="nb-preview-shade" id="nb-bg-shade" style="background: rgba(9,6,15,{{ $resolved['has_bg'] ? $resolved['bg_overlay'] : 0 }})"></div>
                            <div class="nb-preview-card"><i style="width:60%"></i><i></i><i></i><b></b></div>
                        </div>
                    </div>

                    <label class="nb-radio-card">
                        <input type="radio" name="bg_type" value="gradient" @if(!$resolved['has_bg']) checked @endif>
                        Dark purple gradient (default)
                    </label>
                    <label class="nb-radio-card">
                        <input type="radio" name="bg_type" value="image" @if($resolved['has_bg']) checked @endif>
                        Custom image
                    </label>

                    <div class="form-group" style="margin-top:14px">
                        <label for="bg_image" class="control-label">Background image</label>
                        <input type="file" id="bg_image" name="bg_image" class="form-control" accept="image/png,image/jpeg,image/webp">
                        <p class="help-block">PNG, JPG or WebP, up to 8&nbsp;MB. 1920&times;1080 or larger recommended. Uploading an image selects "Custom image" automatically.</p>
                    </div>

                    @if($settings['bg_image'])
                        <div class="checkbox" style="margin-top:0">
                            <label><input type="checkbox" name="remove_bg" value="1"> Remove the current background image</label>
                        </div>
                    @endif

                    <div class="form-group">
                        <label for="bg_overlay" class="control-label">Darkening over the image: <span class="nb-range-value" id="bg_overlay_val">{{ (int) round($resolved['bg_overlay'] * 100) }}</span>%</label>
                        <input type="range" id="bg_overlay" name="bg_overlay" min="0" max="90" step="5" value="{{ (int) round($resolved['bg_overlay'] * 100) }}" style="width:100%">
                        <p class="help-block">Higher values keep the login form easier to read on bright pictures.</p>
                    </div>

                    <div class="form-group">
                        <label for="bg_blur" class="control-label">Blur: <span class="nb-range-value" id="bg_blur_val">{{ $resolved['bg_blur'] }}</span>px</label>
                        <input type="range" id="bg_blur" name="bg_blur" min="0" max="24" step="1" value="{{ $resolved['bg_blur'] }}" style="width:100%">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="box box-footer" style="padding:14px 20px">
        <button type="submit" class="btn btn-primary btn-sm pull-right">Save changes</button>
        <button type="submit" name="reset" value="1" class="btn btn-default btn-sm"
                onclick="return confirm('Reset logo, favicon and login background to the defaults?');">Reset to defaults</button>
    </div>
</form>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var $ = function (id) { return document.getElementById(id); };
            var bind = function (id, cb) { var el = $(id); el.addEventListener('input', function () { cb(el.value); }); };

            bind('logo_height', function (v) { $('logo_height_val').textContent = v; $('nb-logo-img').style.height = v + 'px'; });
            bind('bg_overlay', function (v) { $('bg_overlay_val').textContent = v; $('nb-bg-shade').style.background = 'rgba(9,6,15,' + (v / 100) + ')'; });
            bind('bg_blur', function (v) { $('bg_blur_val').textContent = v; $('nb-bg-img').style.filter = 'blur(' + v + 'px)'; });
            $('show_name').addEventListener('change', function () { $('nb-logo-name').style.display = this.checked ? '' : 'none'; });

            $('logo').addEventListener('change', function () {
                var f = this.files && this.files[0];
                if (!f) { return; }
                var img = $('nb-logo-img');
                img.src = URL.createObjectURL(f);
                img.style.display = '';
            });
            $('bg_image').addEventListener('change', function () {
                var f = this.files && this.files[0];
                if (!f) { return; }
                var el = $('nb-bg-img');
                el.style.backgroundImage = 'url(' + URL.createObjectURL(f) + ')';
                el.style.display = '';
                document.querySelector('input[name=bg_type][value=image]').checked = true;
            });
        })();
    </script>
@endsection
