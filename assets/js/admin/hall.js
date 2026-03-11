/**
 * hall.js — Admin Hall Management JS
 * Module 2 – Riffna
 * Handles: image upload preview, drag-and-drop sort, delete confirms
 */

document.addEventListener('DOMContentLoaded', () => {

    /* ── Image Upload Preview ──────────────────────────────────────────── */
    const fileInput   = document.getElementById('hallImages');
    const previewStrip = document.getElementById('previewStrip');
    const uploadZone  = document.querySelector('.upload-zone');
    let selectedFiles = [];

    if (fileInput) {
        fileInput.addEventListener('change', () => {
            Array.from(fileInput.files).forEach(file => {
                if (!file.type.startsWith('image/')) return;
                selectedFiles.push(file);
                const reader = new FileReader();
                reader.onload = e => addPreview(e.target.result, selectedFiles.length - 1);
                reader.readAsDataURL(file);
            });
        });
    }

    function addPreview(src, idx) {
        if (!previewStrip) return;
        const item = document.createElement('div');
        item.className = 'preview-item';
        item.dataset.idx = idx;
        item.innerHTML = `
            <img src="${src}" alt="Preview">
            <button type="button" class="remove-preview" aria-label="Remove">
                <i class="fa-solid fa-xmark"></i>
            </button>`;
        item.querySelector('.remove-preview').addEventListener('click', () => {
            selectedFiles.splice(idx, 1);
            item.remove();
        });
        previewStrip.appendChild(item);
    }

    /* ── Drag-and-Drop on Upload Zone ─────────────────────────────────── */
    if (uploadZone) {
        uploadZone.addEventListener('dragover', e => {
            e.preventDefault();
            uploadZone.classList.add('drag-over');
        });
        uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('drag-over'));
        uploadZone.addEventListener('drop', e => {
            e.preventDefault();
            uploadZone.classList.remove('drag-over');
            if (fileInput) {
                const dt = e.dataTransfer;
                fileInput.files = dt.files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });
    }

    /* ── Drag-and-Drop Sort on Existing Images ─────────────────────────── */
    const imageGrid = document.querySelector('.image-grid');
    if (imageGrid) {
        let dragging = null;

        imageGrid.querySelectorAll('.image-thumb').forEach(thumb => {
            thumb.setAttribute('draggable', 'true');

            thumb.addEventListener('dragstart', () => {
                dragging = thumb;
                setTimeout(() => thumb.style.opacity = '0.4', 0);
            });

            thumb.addEventListener('dragend', () => {
                dragging.style.opacity = '';
                dragging = null;
                updateSortOrder();
            });

            thumb.addEventListener('dragover', e => {
                e.preventDefault();
                if (dragging && dragging !== thumb) {
                    const rect = thumb.getBoundingClientRect();
                    const mid  = rect.left + rect.width / 2;
                    if (e.clientX < mid) {
                        imageGrid.insertBefore(dragging, thumb);
                    } else {
                        imageGrid.insertBefore(dragging, thumb.nextSibling);
                    }
                }
            });
        });

        function updateSortOrder() {
            const form = document.getElementById('sortOrderForm');
            if (!form) return;
            form.innerHTML = '';
            imageGrid.querySelectorAll('.image-thumb').forEach((thumb, i) => {
                const id = thumb.dataset.imageId;
                const input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = `sort[${id}]`;
                input.value = i;
                form.appendChild(input);
                thumb.querySelector('.img-sort-badge').textContent = i + 1;
            });
        }
    }

    /* ── Delete Image Confirmation ────────────────────────────────────── */
    document.querySelectorAll('.btn-delete-img').forEach(btn => {
        btn.addEventListener('click', () => {
            if (confirm('Delete this image? This cannot be undone.')) {
                const imageId = btn.closest('.image-thumb').dataset.imageId;
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.href;
                const input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = 'delete_image_id';
                input.value = imageId;
                form.appendChild(input);
                const csrf = document.createElement('input');
                csrf.type  = 'hidden';
                csrf.name  = 'action';
                csrf.value = 'delete_image';
                form.appendChild(csrf);
                document.body.appendChild(form);
                form.submit();
            }
        });
    });

});
