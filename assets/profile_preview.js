document.addEventListener('DOMContentLoaded', () => {
    const profileModal = document.getElementById('profile-preview-modal');
    if (!profileModal) return;

    function showModal() {
        // Remove hidden attribute first so CSS can take over
        profileModal.removeAttribute('hidden');
        profileModal.classList.add('active');
    }

    function hideModal() {
        profileModal.classList.remove('active');
        profileModal.setAttribute('hidden', '');
    }

    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('.profile-preview-trigger');
        if (!trigger) return;

        // Skip interactive elements inside the trigger
        if (event.target.closest('button') || event.target.closest('a') || event.target.closest('form') || event.target.closest('details')) return;

        event.preventDefault();
        const userId = trigger.dataset.userId;
        if (!userId || userId === '0') return;

        try {
            const response = await fetch(`../api/get_user_preview.php?user_id=${userId}`);
            if (!response.ok) throw new Error('Network error');
            const result = await response.json();

            if (result.status === 'success') {
                const data = result.data;
                document.getElementById('pp-username').textContent = data.username;
                document.getElementById('pp-bio').textContent = data.bio || 'No bio available.';

                const avatarContainer = document.getElementById('pp-avatar-container');
                if (data.profile_image) {
                    avatarContainer.innerHTML = `<img class="avatar-circle avatar-image" src="${data.profile_image}" alt="${data.username}" style="width:72px;height:72px;object-fit:cover;">`;
                } else {
                    avatarContainer.innerHTML = `<div class="avatar-circle" style="width:72px;height:72px;font-size:1.8rem;display:grid;place-items:center;background:var(--accent);color:white;border-radius:50%;">${data.username.charAt(0).toUpperCase()}</div>`;
                }

                const addFriendBtn = document.getElementById('pp-add-friend-btn');
                const messageLink = document.getElementById('pp-message-link');

                messageLink.href = `messages.php?recipient_id=${data.id}`;
                addFriendBtn.dataset.userId = data.id;

                if (data.is_self) {
                    addFriendBtn.style.display = 'none';
                    messageLink.style.display = 'none';
                } else {
                    addFriendBtn.style.display = '';
                    messageLink.style.display = '';
                    addFriendBtn.disabled = false;
                    addFriendBtn.className = 'primary-button';

                    if (data.friendship_status === 'accepted') {
                        addFriendBtn.textContent = 'Already Friends';
                        addFriendBtn.disabled = true;
                        addFriendBtn.className = 'secondary-action-button';
                    } else if (data.friendship_status === 'pending_sent') {
                        addFriendBtn.textContent = 'Request Sent';
                        addFriendBtn.disabled = true;
                        addFriendBtn.className = 'secondary-action-button';
                    } else if (data.friendship_status === 'pending_received') {
                        addFriendBtn.textContent = 'Respond in Notifications';
                        addFriendBtn.disabled = true;
                        addFriendBtn.className = 'secondary-action-button';
                    } else {
                        addFriendBtn.textContent = 'Add Friend';
                    }
                }

                showModal();
            }
        } catch (error) {
            console.error('Profile preview error:', error);
        }
    });

    // Close on backdrop or X button
    profileModal.addEventListener('click', (e) => {
        if (e.target === profileModal || e.target.classList.contains('preview-modal-backdrop') || e.target.classList.contains('preview-close-button')) {
            hideModal();
        }
    });

    // Handle Add Friend
    const addFriendBtn = document.getElementById('pp-add-friend-btn');
    if (addFriendBtn) {
        addFriendBtn.addEventListener('click', async () => {
            const userId = addFriendBtn.dataset.userId;
            if (!userId) return;
            addFriendBtn.disabled = true;
            addFriendBtn.textContent = 'Sending...';
            try {
                const response = await fetch('../api/send_friend_request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `receiver_id=${userId}`
                });
                const result = await response.json();
                if (result.success) {
                    addFriendBtn.textContent = 'Request Sent';
                    addFriendBtn.className = 'secondary-action-button';
                } else {
                    addFriendBtn.disabled = false;
                    addFriendBtn.textContent = 'Add Friend';
                }
            } catch (error) {
                addFriendBtn.disabled = false;
                addFriendBtn.textContent = 'Add Friend';
            }
        });
    }
});
