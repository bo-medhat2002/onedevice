import os

PROJECT_ROOT = "."  # المجلد الحالي
OUTPUT_FILE = "output.txt"

# مجلدات وملفات نتجاهلها (يمكنك تعديلها)
EXCLUDE_DIRS = {".git", "__pycache__", ".vscode", "node_modules", ".idea", "venv", "dist", "build"}
EXCLUDE_EXTENSIONS = {".exe", ".dll", ".png", ".jpg", ".jpeg", ".gif", ".bmp", ".ico", ".pdf", ".zip", ".tar", ".gz"}

def is_binary_file(file_path):
    """محاولة بسيطة للكشف عن الملفات الثنائية"""
    try:
        with open(file_path, 'rb') as f:
            chunk = f.read(1024)
            if b'\0' in chunk:  # إذا وُجد بايت صفري، غالبًا ملف ثنائي
                return True
        return False
    except Exception:
        return True  # إذا فشل الفتح، نعتبره غير قابل للقراءة

def main():
    file_count = 0
    with open(OUTPUT_FILE, "w", encoding="utf-8") as out_file:
        for root, dirs, files in os.walk(PROJECT_ROOT):
            # تصفية المجلدات المُستبعدة
            dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS and not d.startswith(".")]

            for file in files:
                if file.startswith(".") or file == OUTPUT_FILE:
                    continue

                # استثناء حسب الامتداد
                _, ext = os.path.splitext(file)
                if ext.lower() in EXCLUDE_EXTENSIONS:
                    continue

                file_path = os.path.join(root, file)
                rel_path = os.path.relpath(file_path, PROJECT_ROOT)

                print(f"[+] معالجة: {rel_path}")  # ← هذا سيظهر لك في الـ Terminal

                # تخطي الملفات الثنائية
                if is_binary_file(file_path):
                    print(f"    -> تم تخطيه (ملف ثنائي أو غير نصي)")
                    continue

                try:
                    with open(file_path, "r", encoding="utf-8") as f:
                        content = f.read()
                except Exception as e:
                    print(f"    -> خطأ في القراءة: {e}")
                    content = f"[خطأ في قراءة الملف: {e}]"

                # كتابة إلى الملف الناتج
                out_file.write(f"\n{'='*70}\n")
                out_file.write(f"مسار الملف: {rel_path}\n")
                out_file.write(f"{'='*70}\n")
                out_file.write(content)
                out_file.write("\n\n")
                file_count += 1

    print(f"\n✅ تم جمع {file_count} ملف(ات) نصي(ة) في: {OUTPUT_FILE}")

if __name__ == "__main__":
    main()