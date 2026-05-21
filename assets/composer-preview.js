document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector(".composer-form");
    if (!form) {
        return;
    }

    const body = document.body;
    const previewTrigger = form.querySelector("[data-preview-trigger]");
    const previewConfirm = form.querySelector("[data-preview-confirm]");
    const previewClosers = form.querySelectorAll("[data-preview-close]");
    const modeFields = form.querySelectorAll("[data-composer-mode]");
    const productFieldsPanel = form.querySelector("[data-product-fields]");
    const helperCopy = document.querySelector("[data-composer-helper]");
    const contentLabel = form.querySelector("[data-content-label]");
    const fields = {
        productName: form.querySelector("#product_name"),
        category: form.querySelector("#category"),
        price: form.querySelector("#price"),
        quantity: form.querySelector("#quantity_available"),
        location: form.querySelector("#location_text"),
        contact: form.querySelector("#contact_number"),
        content: form.querySelector("#content"),
        media: form.querySelector("#post_image"),
    };

    const preview = {
        panel: document.querySelector("[data-composer-preview]"),
        empty: document.querySelector("[data-preview-empty]"),
        productCard: document.querySelector("[data-preview-product-card]"),
        productName: document.querySelector("[data-preview-product-name]"),
        category: document.querySelector("[data-preview-category]"),
        price: document.querySelector("[data-preview-price]"),
        quantity: document.querySelector("[data-preview-quantity]"),
        location: document.querySelector("[data-preview-location]"),
        contact: document.querySelector("[data-preview-contact]"),
        content: document.querySelector("[data-preview-content]"),
        imageWrap: document.querySelector("[data-preview-image-wrap]"),
        image: document.querySelector("[data-preview-image]"),
        fileName: document.querySelector("[data-preview-file-name]"),
        composerSelectedFileName: document.querySelector("#composer_selected_file_name"),
    };

    let objectUrl = null;
    let currentMode = form.querySelector("[data-composer-mode]:checked")?.value || "product";

    const toggleVisibility = (element, shouldShow) => {
        if (!element) {
            return;
        }

        element.hidden = !shouldShow;
    };

    const openPreview = () => {
        if (!preview.panel) {
            return;
        }

        preview.panel.hidden = false;
        body.classList.add("modal-open");
    };

    const closePreview = () => {
        if (!preview.panel) {
            return;
        }

        preview.panel.hidden = true;
        body.classList.remove("modal-open");
    };

    const applyMode = () => {
        currentMode = form.querySelector("[data-composer-mode]:checked")?.value || "product";
        const isProductMode = currentMode === "product";

        toggleVisibility(productFieldsPanel, isProductMode);
        if (helperCopy) {
            helperCopy.textContent = isProductMode
                ? "Create a marketplace-style milk post with real product details."
                : "Share a quick community update, tip, question, or photo.";
        }
        if (contentLabel) {
            contentLabel.textContent = isProductMode ? "Description or update" : "What's on your mind?";
        }
        if (fields.content) {
            fields.content.placeholder = isProductMode
                ? "Tell buyers what makes this product special, when it is available, or how to order."
                : "Share an update with the community...";
        }
        if (previewTrigger) {
            previewTrigger.textContent = isProductMode ? "Post Product" : "Post";
        }
        if (previewConfirm) {
            previewConfirm.textContent = isProductMode ? "Confirm Product Post" : "Confirm Post";
        }

        if (!isProductMode) {
            [fields.productName, fields.category, fields.price, fields.quantity, fields.location, fields.contact].forEach((field) => {
                if (field) {
                    field.value = "";
                }
            });
        }
    };

    const updatePreview = () => {
        const isProductMode = currentMode === "product";
        const productName = fields.productName?.value.trim() || "Dairy Product";
        const category = fields.category?.value.trim() || "";
        const price = fields.price?.value.trim() || "";
        const quantity = fields.quantity?.value.trim() || "";
        const location = fields.location?.value.trim() || "";
        const contact = fields.contact?.value.trim() || "";
        const content = fields.content?.value.trim() || "";

        toggleVisibility(preview.productCard, isProductMode);

        if (preview.productName) {
            preview.productName.textContent = productName;
        }
        if (preview.category) {
            preview.category.textContent = category;
        }
        if (preview.price) {
            preview.price.textContent = price;
        }
        if (preview.quantity) {
            preview.quantity.textContent = quantity ? `Quantity: ${quantity}` : "";
        }
        if (preview.location) {
            preview.location.textContent = location ? `Location: ${location}` : "";
        }
        if (preview.contact) {
            preview.contact.textContent = contact ? `Contact: ${contact}` : "";
        }
        if (preview.content) {
            preview.content.textContent = content || "Your description or update will appear here.";
        }

        toggleVisibility(preview.category, isProductMode && category !== "");
        toggleVisibility(preview.price, isProductMode && price !== "");
        toggleVisibility(preview.quantity, isProductMode && quantity !== "");
        toggleVisibility(preview.location, isProductMode && location !== "");
        toggleVisibility(preview.contact, isProductMode && contact !== "");

        const hasProductPreview = isProductMode && (category || price || quantity || location || contact || (fields.productName?.value.trim() || ""));
        const hasTextPreview = hasProductPreview || content;
        toggleVisibility(preview.empty, !hasTextPreview && !(fields.media?.files?.length));
    };

    const updateMediaPreview = () => {
        const file = fields.media?.files?.[0];

        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }

        if (!file) {
            if (preview.image) {
                preview.image.removeAttribute("src");
            }
            if (preview.fileName) {
                preview.fileName.textContent = "";
            }
            if (preview.composerSelectedFileName) {
                preview.composerSelectedFileName.textContent = "";
                preview.composerSelectedFileName.classList.remove("has-file");
            }
            toggleVisibility(preview.imageWrap, false);
            updatePreview();
            return;
        }

        objectUrl = URL.createObjectURL(file);
        if (preview.image) {
            preview.image.src = objectUrl;
            preview.image.alt = file.name;
        }
        if (preview.fileName) {
            preview.fileName.textContent = file.name;
        }
        if (preview.composerSelectedFileName) {
            preview.composerSelectedFileName.textContent = file.name;
            preview.composerSelectedFileName.classList.add("has-file");
        }
        toggleVisibility(preview.imageWrap, true);
        toggleVisibility(preview.empty, false);
    };

    const refreshPreview = () => {
        updatePreview();
        updateMediaPreview();
    };

    [fields.productName, fields.category, fields.price, fields.quantity, fields.location, fields.contact, fields.content].forEach((field) => {
        if (field) {
            field.addEventListener("input", refreshPreview);
            field.addEventListener("change", refreshPreview);
        }
    });

    if (fields.media) {
        fields.media.addEventListener("change", updateMediaPreview);
    }

    modeFields.forEach((field) => {
        field.addEventListener("change", () => {
            applyMode();
            refreshPreview();
        });
    });

    if (preview.panel) {
        preview.panel.hidden = true;
    }

    if (previewTrigger) {
        previewTrigger.addEventListener("click", () => {
            refreshPreview();
            openPreview();
        });
    }

    if (previewConfirm) {
        previewConfirm.addEventListener("click", () => {
            form.submit();
        });
    }

    previewClosers.forEach((closer) => {
        closer.addEventListener("click", closePreview);
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && preview.panel && !preview.panel.hidden) {
            closePreview();
        }
    });

    applyMode();
    refreshPreview();

    // Debug widget removed.
});
