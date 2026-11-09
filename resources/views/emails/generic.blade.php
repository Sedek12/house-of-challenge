<!DOCTYPE html>
<html lang="fr">
<body style="margin:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#1F1F1F">
<div style="max-width:560px;margin:24px auto;background:#fff;border-radius:8px;overflow:hidden">
    <div style="background:#1F1F1F;color:#fff;padding:16px 24px;font-size:18px;font-weight:bold">
        OCD <span style="color:#FE7C02">Live</span> — House of Challenge
    </div>
    <div style="padding:24px;line-height:1.55">
        @foreach ($lines as $line)
            <p style="margin:0 0 12px">{!! preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#B85600">$1</a>', e($line)) !!}</p>
        @endforeach
        @if ($action)
            <p style="margin:20px 0 4px">
                <a href="{{ $action['url'] }}" style="display:inline-block;background:#EE6400;color:#fff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:6px">{{ $action['label'] }}</a>
            </p>
        @endif
    </div>
    <div style="padding:12px 24px;font-size:12px;color:#888;border-top:1px solid #eee">
        OCD : Les Opportunités, C'est Dehors — un concept de Bovann, porté par Bovann Group Togo
    </div>
</div>
</body>
</html>