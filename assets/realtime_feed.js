/**
 * Real-time Feed Updates
 * Polls the get_posts API and dynamically injects new posts into the home page.
 */

document.addEventListener('DOMContentLoaded', () => {
    const feedContainer = document.querySelector('.feed-list');
    if (!feedContainer) return;

    let lastPostId = 0;
    let lastCommentId = parseInt(document.body.dataset.lastCommentId || 0);
    
    // Initialize lastPostId from existing posts
    const updateLastIdFromDOM = () => {
        const posts = document.querySelectorAll('.social-post[data-post-id]');
        if (posts.length > 0) {
            const ids = Array.from(posts).map(p => parseInt(p.dataset.postId));
            lastPostId = Math.max(...ids);
        }
    };

    updateLastIdFromDOM();

    const pluralize = (count, singular) => `${count} ${singular}${count === 1 ? '' : 's'}`;

    const updateMetrics = (post, data) => {
        const likeMetric = post.querySelector('.metric-like');
        if (likeMetric) likeMetric.textContent = pluralize(data.like_count, 'like');
        
        const commentMetric = post.querySelector('.metric-comment');
        if (commentMetric) commentMetric.textContent = pluralize(data.comment_count, 'comment');
        
        const shareMetric = post.querySelector('.metric-share');
        if (shareMetric) shareMetric.textContent = pluralize(data.share_count, 'share');

        // Update like button state for current user
        const likeBtn = post.querySelector('.js-async-form[data-action-type="like"] .action-button');
        if (likeBtn) {
            likeBtn.classList.toggle('active-action', parseInt(data.user_liked) === 1);
        }
    };

    const buildAvatar = (username, profileImage, extraClass = '') => {
        if (profileImage) {
            return `<img class="avatar-circle ${extraClass} avatar-image" src="${profileImage}" alt="${username} profile picture">`;
        }
        const initial = (username || 'U').trim().charAt(0).toUpperCase();
        return `<div class="avatar-circle ${extraClass}">${initial}</div>`;
    };

    const escapeHtml = (text) => {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    const buildPostHTML = (item, currentUserId) => {
        const isOwnPost = parseInt(item.user_id) === currentUserId;
        const hasProduct = item.product_name || item.category || item.price || item.quantity_available || item.location_text || item.contact_number;
        const hasShared = item.shared_post_id;

        let productHtml = '';
        if (hasProduct) {
            productHtml = `
                <div class="product-card-box">
                    <div class="product-card-head">
                        <div>
                            ${item.category ? `<span class="product-category-badge">${escapeHtml(item.category)}</span>` : ''}
                            <h4>${escapeHtml(item.product_name) || 'Dairy Product'}</h4>
                        </div>
                        ${item.price ? `<span class="product-price-tag">${escapeHtml(item.price)}</span>` : ''}
                    </div>
                    <div class="product-meta-grid">
                        ${item.quantity_available ? `<span><strong>Quantity:</strong> ${escapeHtml(item.quantity_available)}</span>` : ''}
                        ${item.location_text ? `<span><strong>Location:</strong> ${escapeHtml(item.location_text)}</span>` : ''}
                        ${item.contact_number ? `<span><strong>Contact:</strong> ${escapeHtml(item.contact_number)}</span>` : ''}
                    </div>
                    ${!isOwnPost ? `
                        <div class="product-card-actions">
                            <a class="message-seller-button" href="messages.php?recipient_id=${item.user_id}&post_id=${item.id}">Message Seller</a>
                        </div>` : ''}
                </div>`;
        }

        let sharedHtml = '';
        if (hasShared) {
            sharedHtml = `
                <div class="shared-post-box profile-preview-trigger" data-user-id="${item.shared_user_id || ''}" style="cursor:pointer;">
                    <strong>${escapeHtml(item.shared_username) || 'Original Post'}</strong>
                    ${item.shared_category ? `<span class="product-category-badge inline-badge">${escapeHtml(item.shared_category)}</span>` : ''}
                    ${item.shared_product_name ? `<h4>${escapeHtml(item.shared_product_name)}</h4>` : ''}
                    ${item.shared_price ? `<p><strong>Price:</strong> ${escapeHtml(item.shared_price)}</p>` : ''}
                    ${item.shared_quantity_available ? `<p><strong>Quantity:</strong> ${escapeHtml(item.shared_quantity_available)}</p>` : ''}
                    ${item.shared_location_text ? `<p><strong>Location:</strong> ${escapeHtml(item.shared_location_text)}</p>` : ''}
                    ${item.shared_contact_number ? `<p><strong>Contact:</strong> ${escapeHtml(item.shared_contact_number)}</p>` : ''}
                    ${item.shared_content ? `<p>${escapeHtml(item.shared_content).replace(/\n/g, '<br>')}</p>` : ''}
                    ${item.shared_image_path ? `<img class="shared-post-image" src="${escapeHtml(item.shared_image_path)}" alt="Shared post image">` : ''}
                </div>`;
        }

        let commentsHtml = '';
        if (item.recent_comments && item.recent_comments.length > 0) {
            commentsHtml = item.recent_comments.map(c => `
                <div class="comment-row">
                    <a href="profile.php?user_id=${c.user_id}" style="display:flex; gap:12px; align-items:flex-start; flex:1; text-decoration:none; color:inherit;">
                        ${buildAvatar(c.username, c.profile_image, 'comment-avatar')}
                        <div class="comment-bubble">
                            <strong>${escapeHtml(c.username)}</strong>
                            <p>${escapeHtml(c.content).replace(/\n/g, '<br>')}</p>
                        </div>
                    </a>
                </div>
            `).join('');
        }

        return `
            <article class="social-post new-post-anim" id="post-${item.id}" data-post-id="${item.id}" style="opacity:0; transform:translateY(-20px); transition: all 0.5s ease-out;">
                <div class="post-top">
                    <a href="profile.php?user_id=${item.user_id}" class="post-identity" style="text-decoration:none; color:inherit;">
                        ${buildAvatar(item.username, item.profile_image, 'small-avatar')}
                        <div>
                            <h3>${escapeHtml(item.username)}</h3>
                            <p class="muted small-copy">${escapeHtml(item.created_at)}</p>
                        </div>
                    </a>
                    ${isOwnPost ? `
                        <details class="post-menu">
                            <summary class="menu-button">&#8226;&#8226;&#8226;</summary>
                            <div class="menu-panel">
                                <a class="menu-link" href="edit_post.php?post_id=${item.id}">Edit</a>
                                <form method="post" action="delete_post.php" class="menu-form">
                                    <input type="hidden" name="post_id" value="${item.id}">
                                    <button type="submit" class="menu-delete">Delete</button>
                                </form>
                            </div>
                        </details>` : ''}
                </div>

                ${hasShared ? `<p class="shared-label">Shared a post from ${escapeHtml(item.shared_username) || 'another seller'}</p>` : ''}
                ${productHtml}
                ${item.content ? `<p class="post-content">${escapeHtml(item.content).replace(/\n/g, '<br>')}</p>` : ''}
                ${item.image_path ? `<img class="post-image" src="${escapeHtml(item.image_path)}" alt="Post image">` : ''}
                ${sharedHtml}

                <div class="post-metrics">
                    <span class="metric-like">${item.like_count} like${parseInt(item.like_count) === 1 ? '' : 's'}</span>
                    <span class="metric-comment">${item.comment_count} comment${parseInt(item.comment_count) === 1 ? '' : 's'}</span>
                    <span class="metric-share">${item.share_count} share${parseInt(item.share_count) === 1 ? '' : 's'}</span>
                </div>

                <div class="post-action-bar">
                    <form method="post" action="toggle_like.php" class="action-form third-width js-async-form" data-action-type="like">
                        <input type="hidden" name="post_id" value="${item.id}">
                        <button type="submit" class="action-button ${item.user_liked ? 'active-action' : ''}">Like</button>
                    </form>
                    <a href="#comment-${item.id}" class="action-link comment-link third-width">Comment</a>
                    <form method="post" action="share_post.php" class="action-form third-width js-async-form" data-action-type="share">
                        <input type="hidden" name="post_id" value="${item.id}">
                        <button type="submit" class="action-button">Share</button>
                    </form>
                </div>

                <div class="comment-list">
                    ${commentsHtml}
                </div>
                <form method="post" action="add_comment.php" class="comment-form js-async-form" data-action-type="comment" id="comment-${item.id}">
                    <input type="hidden" name="post_id" value="${item.id}">
                    <input type="text" name="content" placeholder="Write a comment...">
                    <button type="submit" class="comment-submit">Comment</button>
                </form>
            </article>
        `;
    };

    const fetchUpdates = async () => {
        try {
            // 1. Fetch NEW posts
            const postResponse = await fetch(`../api/get_posts.php?last_id=${lastPostId}`);
            const postResult = await postResponse.json();
            const currentUserId = parseInt(document.body.dataset.userId || 0);

            if (postResult.status === 'success' && postResult.data.length > 0) {
                const emptyFeed = document.querySelector('.empty-feed');
                if (emptyFeed) emptyFeed.remove();

                postResult.data.reverse().forEach(post => {
                    if (document.getElementById(`post-${post.id}`)) return;
                    
                    const temp = document.createElement('div');
                    temp.innerHTML = buildPostHTML(post, currentUserId);
                    const postElem = temp.firstElementChild;
                    feedContainer.prepend(postElem);
                    
                    setTimeout(() => {
                        postElem.style.opacity = '1';
                        postElem.style.transform = 'translateY(0)';
                    }, 50);

                    if (post.id > lastPostId) lastPostId = post.id;
                });
            }

            // 2. Fetch engagement updates for visible posts
            const visiblePosts = document.querySelectorAll('.social-post[data-post-id]');
            const visibleIds = Array.from(visiblePosts).map(p => p.dataset.postId).join(',');
            
            if (visibleIds) {
                const updateResponse = await fetch(`../api/get_feed_updates.php?ids=${visibleIds}&last_comment_id=${lastCommentId}`);
                const updateResult = await updateResponse.json();

                if (updateResult.status === 'success') {
                    // Update metrics
                    Object.keys(updateResult.metrics).forEach(id => {
                        const postElem = document.getElementById(`post-${id}`);
                        if (postElem) updateMetrics(postElem, updateResult.metrics[id]);
                    });

                    // Prepend new comments
                    updateResult.new_comments.forEach(comment => {
                        if (lastCommentId < comment.id) lastCommentId = comment.id;
                        
                        const postElem = document.getElementById(`post-${comment.post_id}`);
                        if (postElem) {
                            const postComments = postElem.querySelector('.comment-list');
                            if (postComments) {
                                // Avoid adding our own comment if it's already there from async-form
                                const existing = Array.from(postComments.querySelectorAll('.comment-bubble p')).some(p => p.textContent === comment.content);
                                if (!existing) {
                                    const row = document.createElement('div');
                                    row.className = 'comment-row';
                                    row.style.opacity = '0';
                                    row.style.transition = 'opacity 0.5s ease-in';
                                    row.innerHTML = `
                                        <div class="profile-preview-trigger" data-user-id="${comment.user_id}" style="cursor:pointer; display:flex; gap:12px; align-items:flex-start; flex:1;">
                                            ${buildAvatar(comment.username, comment.profile_image, 'comment-avatar')}
                                            <div class="comment-bubble">
                                                <strong>${comment.username}</strong>
                                                <p>${comment.content.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</p>
                                            </div>
                                        </div>
                                    `;
                                    postComments.appendChild(row);
                                    setTimeout(() => row.style.opacity = '1', 50);
                                }
                            }
                        }
                    });
                }
            }
        } catch (error) {
            console.error('Error fetching updates:', error);
        }
    };

    setInterval(fetchUpdates, 5000); // Poll every 5 seconds for full real-time feel
});
