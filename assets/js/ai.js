/**
 * StudyMe AI Client & Interactive Learning Assistant
 * Handles AI Tutor interactions, concept explanations, and lesson assistance.
 */

const StudyMeAI = (() => {
    // Generate simulated contextual intelligent response based on topic/question
    function generateSmartResponse(query, context = '') {
        const lower = query.toLowerCase();
        
        if (lower.includes('explain') || lower.includes('what is') || lower.includes('how')) {
            return {
                title: "Concept Clarification",
                text: `Here is a clear breakdown for "${query}":\n\n• **Core Idea:** In the context of ${context || 'modern education'}, think of this as a structured feedback system where inputs are processed iteratively to deliver reliable outcomes.\n• **Analogy:** Imagine building a skyscraper—you need a solid blueprint (architecture) before laying the foundation (database) and painting the exterior (UI design).\n• **Key Takeaway:** Always prioritize separation of concerns and validate your assumptions at each step.`,
                suggestedAction: "Practice Quiz",
                actionLink: "#practice"
            };
        } else if (lower.includes('code') || lower.includes('example') || lower.includes('syntax')) {
            return {
                title: "Code Example & Breakdown",
                text: `Here is an implementation example related to ${context || 'your study topic'}:\n\n\`\`\`javascript\n// Secure async handler pattern\nasync function handleStudyAction(lessonId) {\n    try {\n        const response = await fetch('/api/complete-lesson.php', {\n            method: 'POST',\n            headers: { 'Content-Type': 'application/json' },\n            body: JSON.stringify({ lesson_id: lessonId })\n        });\n        return await response.json();\n    } catch (error) {\n        console.error('Study error:', error);\n    }\n}\n\`\`\`\nThis pattern prevents UI lockup while ensuring secure data transport.`,
                suggestedAction: "Run in Sandbox",
                actionLink: "#sandbox"
            };
        } else if (lower.includes('quiz') || lower.includes('test') || lower.includes('practice')) {
            return {
                title: "Personalized Practice Question",
                text: `Let's test your comprehension:\n\n**Question:** When scaling a modern web application, what is the primary benefit of stateless sessions stored in secure cookies or JWTs?\n\n**A)** It allows any server in a cluster to handle requests seamlessly.\n**B)** It increases MySQL query latency.\n**C)** It eliminates the need for CSS variables.\n\n*Reply with your answer (A, B, or C) to verify!*`,
                suggestedAction: "Submit Answer",
                actionLink: "#submit"
            };
        } else {
            return {
                title: "StudyMe AI Assistant",
                text: `I understand you're asking about "${query}" in relation to **${context || 'your current course'}**.\n\nStudyMe recommends focusing on the core principles taught in Module 1, specifically checking the hands-on code exercises and taking the topic quiz to solidify your understanding.`,
                suggestedAction: "View Lesson Notes",
                actionLink: "#notes"
            };
        }
    }

    return {
        ask: (prompt, context = '') => {
            return new Promise((resolve) => {
                // Simulate intelligent processing delay
                setTimeout(() => {
                    const response = generateSmartResponse(prompt, context);
                    if (typeof StudyMeFeedback !== 'undefined') {
                        StudyMeFeedback.notification();
                    }
                    resolve(response);
                }, 750);
            });
        }
    };
})();

// Attach event listeners for AI drawers and quick prompts
document.addEventListener("DOMContentLoaded", () => {
    const aiForm = document.getElementById("aiQueryForm");
    const aiInput = document.getElementById("aiQueryInput");
    const aiChatLog = document.getElementById("aiChatLog");

    if (aiForm && aiInput && aiChatLog) {
        aiForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const text = aiInput.value.trim();
            if (!text) return;

            // Append User Message
            const userMsg = document.createElement("div");
            userMsg.className = "chat-bubble chat-bubble-user mb-3";
            userMsg.textContent = text;
            aiChatLog.appendChild(userMsg);
            aiInput.value = "";
            aiChatLog.scrollTop = aiChatLog.scrollHeight;

            // Loading Indicator
            const loader = document.createElement("div");
            loader.className = "d-flex align-items-center gap-2 text-white-50 small my-2 ai-loading";
            loader.innerHTML = '<div class="spinner-grow spinner-grow-sm text-warning" role="status"></div><span>AI is thinking...</span>';
            aiChatLog.appendChild(loader);
            aiChatLog.scrollTop = aiChatLog.scrollHeight;

            // Get Context from data attribute
            const context = aiForm.dataset.context || '';
            const res = await StudyMeAI.ask(text, context);

            loader.remove();

            // Append AI Message
            const aiMsg = document.createElement("div");
            aiMsg.className = "chat-bubble chat-bubble-ai mb-3";
            aiMsg.innerHTML = `
                <div class="fw-bold mb-1 text-warning"><i class="bi bi-stars me-1"></i> ${res.title}</div>
                <div style="white-space: pre-line;">${res.text}</div>
            `;
            aiChatLog.appendChild(aiMsg);
            aiChatLog.scrollTop = aiChatLog.scrollHeight;
        });
    }
});
