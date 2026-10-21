# Inbox — งานสำหรับ Hermes

รูปแบบดูที่ [HANDOFF_PROTOCOL.md](HANDOFF_PROTOCOL.md) · เพิ่มรายการใหม่ท้ายไฟล์

---

## 2026-10-21 — ตรวจ MEMORY.md ให้ตรงกับโปรเจกต์
**From:** claude
**Status:** open
**Approved by user:** no
**Task:** ตรวจว่า `~/.hermes/memories/MEMORY.md` ยังตรงกับโครงสร้างโปรเจกต์ปัจจุบัน (มี `.claude/agents/inspector.md` และโฟลเดอร์ `orchestration/` เพิ่มเข้ามาแล้ว) แล้วรายงานใน outbox — ยังไม่ต้องแก้ไฟล์ memory จนกว่าผู้ใช้อนุมัติ
**Files:** `AGENTS.md`, `.hermes.md`, `orchestration/*`
**Done when:** มีรายงานใน `outbox.md`
