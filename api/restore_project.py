import os
import re

INPUT_FILE = "output.txt"

def main():
    if not os.path.isfile(INPUT_FILE):
        print(f"❌ الملف '{INPUT_FILE}' غير موجود!")
        return

    with open(INPUT_FILE, "r", encoding="utf-8") as f:
        content = f.read()

    # تقسيم الملف إلى أقسام كل منها يبدأ بمسار ملف
    # نستخدم السطر المميز: "مسار الملف: ..."
    sections = re.split(r'\n={70}\nمسار الملف: (.*?)\n={70}\n', content)[1:]

    if len(sections) % 2 != 0:
        print("⚠️  تنسيق الملف غير متوقع — قد يكون تالفًا.")
        return

    restored_count = 0
    for i in range(0, len(sections), 2):
        rel_path = sections[i].strip()
        file_content = sections[i + 1]

        # تنظيف المسار من أية أحرف غير صحيحة (مثل مسافات زائدة)
        rel_path = rel_path.strip()
        if not rel_path:
            continue

        # تأمين المسار (تجنب خروج الملفات خارج المجلد)
        if os.path.isabs(rel_path) or '..' in rel_path:
            print(f"⚠️  مسار غير آمن تم تجاهله: {rel_path}")
            continue

        # إنشاء المجلدات إذا لم تكن موجودة
        dir_path = os.path.dirname(rel_path)
        if dir_path:
            os.makedirs(dir_path, exist_ok=True)

        # كتابة الملف
        try:
            with open(rel_path, "w", encoding="utf-8") as out_file:
                # إزالة السطر الفارغ الأخير إذا كان موجودًا بسبب "\n\n" في النهاية
                if file_content.endswith("\n\n"):
                    file_content = file_content[:-1]  # نحذف سطرًا فارغًا واحدًا فقط (احتفظ بآخر سطر إن وُجد)
                out_file.write(file_content)
            print(f"[✓] تم استعادة: {rel_path}")
            restored_count += 1
        except Exception as e:
            print(f"[✗] فشل استعادة {rel_path}: {e}")

    print(f"\n✅ تم استعادة {restored_count} ملف(ات) بنجاح!")

if __name__ == "__main__":
    main()