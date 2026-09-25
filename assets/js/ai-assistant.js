/**
 * PartoCMS - AI Assistant Frontend
 * تحلیل خطا با Ollama
 */
async function analyzeWithAI() {
    const btn = document.getElementById("aiBtn");
    const resultBox = document.getElementById("aiResult");
    const resultBody = document.getElementById("aiResultBody");

    if (!btn || !resultBox || !resultBody) {
        alert("خطا: عناصر AI یافت نشد. لطفاً صفحه را رفرش کنید.");
        return;
    }

    // حالت loading
    btn.disabled = true;
    const originalText = btn.innerHTML;
    btn.innerHTML = '⏳ در حال تحلیل...';

    resultBox.style.display = "block";
    resultBody.innerHTML = '<div style="text-align:center;padding:20px;color:#0891b2">' +
        '<div style="font-size:30px;margin-bottom:10px">🤖</div>' +
        '<div>مدل در حال تحلیل خطاست...</div>' +
        '<div style="font-size:11px;color:#94a3b8;margin-top:8px">این ممکن است ۵ تا ۳۰ ثانیه طول بکشد</div>' +
        '</div>';
    resultBox.scrollIntoView({ behavior: "smooth", block: "center" });

    // خواندن داده‌ها
    const errorText = document.querySelector(".error-msg")?.textContent || "";
    const tableName = document.getElementById("tableName")?.value || "";
    const columns = [];
    document.querySelectorAll(".col-row").forEach(row => {
        const name = row.querySelector(".col-name")?.value || "";
        const type = row.querySelector(".col-type")?.value || "";
        if (name) columns.push({ name, type });
    });

    try {
        const res = await fetch("/ajax/ai_analyze.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                error: errorText,
                table: tableName,
                columns: columns,
            }),
        });

        const data = await res.json();

        if (!data.ok) {
            resultBody.innerHTML = '<div style="color:#dc2626;padding:10px">❌ ' + escapeHtml(data.error || "خطای نامشخص") + '</div>';
        } else {
            const a = data.analysis;
            let html = "";

            if (a.summary) {
                html += '<div style="background:#ecfeff;border-right:4px solid #06b6d4;padding:12px 16px;border-radius:8px;margin-bottom:15px"><strong>📌 خلاصه:</strong><br>' + escapeHtml(a.summary) + '</div>';
            }
            if (a.cause) {
                html += '<div style="margin-bottom:12px"><strong style="color:#dc2626">🔍 علت اصلی:</strong><br>' + escapeHtml(a.cause) + '</div>';
            }
            if (a.solution) {
                html += '<div style="background:#f0fdf4;border-right:4px solid #10b981;padding:12px 16px;border-radius:8px;margin-bottom:12px"><strong style="color:#059669">✅ راه‌حل:</strong><br>' + escapeHtml(a.solution).replace(/\n/g, "<br>") + '</div>';
            }
            if (a.field) {
                html += '<div style="font-size:12px;color:#64748b">📍 فیلد: <code style="background:#f1f5f9;padding:2px 8px;border-radius:4px">' + escapeHtml(a.field) + '</code></div>';
            }
            if (data.model) {
                html += '<div style="font-size:11px;color:#94a3b8;margin-top:15px;text-align:left">🤖 مدل: ' + escapeHtml(data.model) + '</div>';
            }

            resultBody.innerHTML = html;
        }
    } catch (err) {
        resultBody.innerHTML = '<div style="color:#dc2626;padding:10px">❌ خطای شبکه: ' + escapeHtml(err.message) + '</div>';
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
}
