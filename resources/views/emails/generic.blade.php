<!DOCTYPE html>
<html lang="fr">
<body style="margin:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#1F1F1F">
<div style="max-width:560px;margin:24px auto;background:#fff;border-radius:8px;overflow:hidden">
    <div style="background:#1F1F1F;color:#fff;padding:16px 24px;font-size:18px;font-weight:bold">
        House of <span style="color:#C8662B">Challenge</span>
    </div>
    <div style="padding:24px;line-height:1.55">
        @foreach ($lines as $line)
            <p style="margin:0 0 12px">{{ $line }}</p>
        @endforeach
    </div>
    <div style="padding:12px 24px;font-size:12px;color:#888;border-top:1px solid #eee">
        Émission en live TikTok — Studio OCD Live, Togo
    </div>
</div>
</body>
</html>
