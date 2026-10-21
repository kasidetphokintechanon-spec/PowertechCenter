# Handoff Protocol — Claude ↔ Hermes

ไฟล์ในโฟลเดอร์นี้คือช่องทางส่งงานระหว่าง Main Orchestrator (Claude) กับ Co-Orchestrator (Hermes) ผู้ใช้อนุมัติทุก phase

## ไฟล์

| ไฟล์ | ใครเขียน | ใครอ่าน | ใช้ทำอะไร |
|------|----------|---------|-----------|
| `inbox.md` | Claude / User | Hermes | สั่งงานให้ Hermes |
| `outbox.md` | Hermes | Claude / User | Hermes รายงานผล |
| `.lock` | ผู้ที่กำลังแก้ไฟล์ | ทุกคน | กันแก้ไฟล์ชนกัน |
| `last_session.md` | hook ท้าย session | ทุกคน | สรุป session ล่าสุด (gitignored) |

## รูปแบบ inbox (Claude/User → Hermes)

เพิ่มรายการใหม่ไว้**ท้ายไฟล์** ห้ามลบของเก่า — เปลี่ยน Status เมื่อทำเสร็จ

```markdown
## YYYY-MM-DD HH:MM — <ชื่องาน>
**From:** claude | user
**Status:** open | in-progress | done | cancelled
**Approved by user:** yes | no
**Task:** <สิ่งที่ต้องทำ>
**Files:** <ไฟล์ที่เกี่ยวข้อง>
**Done when:** <เงื่อนไขว่าเสร็จ>
```

งาน deploy, commit, schema change ต้องมี `**Approved by user:** yes` เท่านั้น Hermes จึงจะทำได้

## รูปแบบ outbox (Hermes → Claude)

```markdown
## YYYY-MM-DD HH:MM — <ชื่องาน>
**Status:** done | blocked | needs-claude
**Summary:** ...
**Files touched:** ...
**Next for Claude:** ...
```

## Lock

ก่อนแก้ไฟล์ ให้ดู `.lock` — ถ้าไม่มีไฟล์นี้ = ไม่มีใครล็อก

```
owner: claude | hermes
since: YYYY-MM-DD HH:MM
files:
  - stock_management.html
  - api/actions/stock/*
reason: <ทำอะไรอยู่>
```

- ห้ามแก้ไฟล์ที่อยู่ใน `files:` ถ้า `owner` เป็นอีกฝั่ง
- ลบ `.lock` ทันทีเมื่อทำเสร็จ
- lock เก่าเกิน 24 ชม. ให้ถามผู้ใช้ก่อนลบ

## Inspector

ก่อนส่งงานใหญ่ให้ผู้ใช้ ให้ Claude เรียก Inspector (`.claude/agents/inspector.md`) ตรวจ
ถ้า Inspector ส่ง `[TO-DEV]` กลับมา Claude ต้องแก้หรืออธิบายเหตุผลก่อนส่ง
