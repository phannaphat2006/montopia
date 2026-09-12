<!doctype html>
<html lang="th">
<body style="font-family:Arial,sans-serif;color:#182126;line-height:1.7">
    <h1 style="font-size:22px">มีความคืบหน้าใหม่ในโครงการ</h1>
    <p><strong>{{ $project->project_name }}</strong></p>
    <p>{{ $activity->title }}</p>
    @if($activity->body)<p>{{ $activity->body }}</p>@endif
    @if($activity->progress_percent !== null)<p>ความคืบหน้าปัจจุบัน: <strong>{{ $activity->progress_percent }}%</strong></p>@endif
    <p><a href="{{ url('/workspace') }}">เข้าสู่ Client Workspace เพื่อดูรายละเอียด</a></p>
    <p style="font-size:12px;color:#657078">อีเมลนี้ส่งจากระบบ MONSTOPIA โดยอัตโนมัติ</p>
</body>
</html>
