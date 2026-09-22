
const StudyMeSkeleton = {

    renderCategorySkeletons(count = 4) {
        let html = '';
        for (let i = 0; i < count; i++) {
            html += `
                <div class="col-sm-6 col-lg-3">
                    <div class="skeleton-category-card shadow-sm">
                        <div class="skeleton-block skeleton-icon-lg"></div>
                        <div class="skeleton-block skeleton-line title"></div>
                        <div class="skeleton-block skeleton-line full"></div>
                        <div class="skeleton-block skeleton-line medium mb-4"></div>
                        <div class="skeleton-block skeleton-btn"></div>
                    </div>
                </div>
            `;
        }
        return html;
    },

    renderCourseSkeletons(count = 6) {
        let html = '';
        for (let i = 0; i < count; i++) {
            html += `
                <div class="col-md-6 col-lg-4">
                    <div class="skeleton-course-card shadow-sm">
                        <div class="skeleton-block skeleton-thumb"></div>
                        <div class="skeleton-body">
                            <div class="d-flex justify-content-between mb-2">
                                <div class="skeleton-block skeleton-pill"></div>
                                <div class="skeleton-block skeleton-pill" style="width:50px;"></div>
                            </div>
                            <div class="skeleton-block skeleton-line title"></div>
                            <div class="skeleton-block skeleton-line full"></div>
                            <div class="skeleton-block skeleton-line medium mb-3"></div>
                            <div class="d-flex justify-content-between align-items-center pt-2 mb-3 border-top border-subtle">
                                <div class="skeleton-block skeleton-line short" style="height:16px;"></div>
                                <div class="skeleton-block skeleton-line short" style="height:20px; width:70px;"></div>
                            </div>
                            <div class="d-flex gap-2 mt-auto">
                                <div class="skeleton-block skeleton-btn" style="flex:1;"></div>
                                <div class="skeleton-block skeleton-btn" style="flex:1;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        return html;
    },

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            const skeletons = document.querySelectorAll('.skeleton-loading-container');
            skeletons.forEach(sk => {
                const targetContent = document.querySelector(sk.getAttribute('data-target'));
                if (targetContent) {
                    setTimeout(() => {
                        sk.style.display = 'none';
                        targetContent.classList.remove('d-none');
                        targetContent.classList.add('skeleton-loaded');
                    }, 150);
                }
            });
        });
    }
};

StudyMeSkeleton.init();
window.StudyMeSkeleton = StudyMeSkeleton;
