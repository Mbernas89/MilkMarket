document.addEventListener('DOMContentLoaded', () => {
    const pluralize = (count, singular) => `${count} ${singular}${count === 1 ? '' : 's'}`;

    const buildAvatar = (username, profileImage, extraClass = '') => {
        if (profileImage) {
            return `<img class="avatar-circle ${extraClass} avatar-image" src="${profileImage}" alt="${username} profile picture">`;
        }

        const initial = (username || 'U').trim().charAt(0).toUpperCase();
        return `<div class="avatar-circle ${extraClass}">${initial}</div>`;
    };

    const updateMetrics = (post, data) => {
        if (typeof data.like_count === 'number') {
            const likeMetric = post.querySelector('.metric-like');
            if (likeMetric) {
                likeMetric.textContent = pluralize(data.like_count, 'like');
            }
        }

        if (typeof data.comment_count === 'number') {
            const commentMetric = post.querySelector('.metric-comment');
            if (commentMetric) {
                commentMetric.textContent = pluralize(data.comment_count, 'comment');
            }
        }

        if (typeof data.share_count === 'number') {
            const shareMetric = post.querySelector('.metric-share');
            if (shareMetric) {
                shareMetric.textContent = pluralize(data.share_count, 'share');
            }
        }
    };

    // Use event delegation for comment focus links
    document.addEventListener('click', (event) => {
        const link = event.target.closest('.comment-link');
        if (!link) return;

        event.preventDefault();
        const targetSelector = link.getAttribute('href');
        if (!targetSelector) return;

        const targetForm = document.querySelector(targetSelector);
        const input = targetForm ? targetForm.querySelector('input[name="content"]') : null;
        if (input) {
            input.focus();
        }
    });

    // Use event delegation for async forms (likes and comments)
    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('.js-async-form');
        if (!form) return;

        event.preventDefault();

        const post = form.closest('.social-post');
        if (!post) return;

        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Action failed');
            }

            updateMetrics(post, data);

            if (form.dataset.actionType === 'like') {
                const button = form.querySelector('.action-button');
                if (button) {
                    button.classList.toggle('active-action', Boolean(data.liked));
                }
            }

            if (form.dataset.actionType === 'comment' && data.comment) {
                const commentList = post.querySelector('.comment-list');
                if (commentList) {
                    const row = document.createElement('div');
                    row.className = 'comment-row';
                    row.innerHTML = `
                        ${buildAvatar(data.comment.username, data.comment.profile_image, 'comment-avatar')}
                        <div class="comment-bubble">
                            <strong>${data.comment.username}</strong>
                            <p>${data.comment.content.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</p>
                        </div>
                    `;
                    commentList.appendChild(row);
                }

                form.reset();
                const input = form.querySelector('input[name="content"]');
                if (input) {
                    input.focus();
                }
            }
        } catch (error) {
            console.error(error);
            alert('That action could not be completed right now.');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    });

});
