/**
 * WooCommerce Product Media Manager - Admin JavaScript
 */

(function($) {
    'use strict';

    let wcPMM = {
        mediaData: [],
        mediaModal: null,
        selectedMediaItems: [],
        
        init: function() {
            this.bindEvents();
            this.initSortable();
            this.loadExistingMedia();
        },
        
        bindEvents: function() {
            // Drop zone events
            $('#wc-pmm-drop-zone').on('click', this.openFileDialog.bind(this));
            $('#wc-pmm-drop-zone').on('dragover', this.handleDragOver.bind(this));
            $('#wc-pmm-drop-zone').on('dragleave', this.handleDragLeave.bind(this));
            $('#wc-pmm-drop-zone').on('drop', this.handleDrop.bind(this));
            
            // File input change
            $('#wc-pmm-file-input').on('change', this.handleFileSelect.bind(this));
            
            // Select from media library
            $('#wc-pmm-select-images').on('click', this.openMediaLibrary.bind(this));
            
            // Table actions
            $(document).on('click', '.wc-pmm-generate-watermark', this.generateWatermark.bind(this));
            $(document).on('click', '.wc-pmm-delete-image', this.deleteImage.bind(this));
            $(document).on('change', '.wc-pmm-sku-input', this.updateSKU.bind(this));
            
            // Bulk watermark button - using direct binding
            $(document).on('click', '#wc-pmm-bulk-watermark', function(e) {
                console.log('Bulk watermark button clicked');
                wcPMM.bulkGenerateWatermarks(e);
            });
            
            // Form submission
            $('#post').on('submit', this.saveMediaData.bind(this));
            
            // Prevent default drag behaviors
            $(document).on('dragover drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
        },
        
        initSortable: function() {
            $('#wc-pmm-media-tbody').sortable({
                handle: '.wc-pmm-sort-handle',
                placeholder: 'ui-sortable-placeholder',
                update: this.updateOrder.bind(this)
            });
        },
        
        loadExistingMedia: function() {
            let existingData = $('#wc-pmm-media-data').val();
            if (existingData) {
                try {
                    this.mediaData = JSON.parse(existingData);
                } catch (e) {
                    this.mediaData = [];
                }
            }
        },
        
        openFileDialog: function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('#wc-pmm-file-input').click();
        },
        
        handleDragOver: function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('#wc-pmm-drop-zone').addClass('dragover');
        },
        
        handleDragLeave: function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('#wc-pmm-drop-zone').removeClass('dragover');
        },
        
        handleDrop: function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('#wc-pmm-drop-zone').removeClass('dragover');
            
            let files = e.originalEvent.dataTransfer.files;
            this.processFiles(files);
        },
        
        handleFileSelect: function(e) {
            let files = e.target.files;
            this.processFiles(files);
        },
        
        processFiles: function(files) {
            if (files.length === 0) return;
            
            this.showProgress();
            let totalFiles = files.length;
            let processedFiles = 0;
            
            Array.from(files).forEach((file, index) => {
                if (this.isValidImageFile(file)) {
                    this.uploadFile(file, function() {
                        processedFiles++;
                        let progress = (processedFiles / totalFiles) * 100;
                        wcPMM.updateProgress(progress);
                        
                        if (processedFiles === totalFiles) {
                            setTimeout(() => {
                                wcPMM.hideProgress();
                            }, 1000);
                        }
                    });
                } else {
                    processedFiles++;
                    this.showError(wc_pmm_ajax.strings.error + ': Invalid file type - ' + file.name);
                }
            });
        },
        
        isValidImageFile: function(file) {
            let allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            return allowedTypes.includes(file.type);
        },
        
        uploadFile: function(file, callback) {
            let formData = new FormData();
            formData.append('action', 'wc_pmm_upload_image');
            formData.append('file', file);
            formData.append('nonce', wc_pmm_ajax.nonce);
            
            $.ajax({
                url: wc_pmm_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        this.addMediaItem(response.data);
                        callback();
                    } else {
                        this.showError(response.data);
                        callback();
                    }
                },
                error: () => {
                    this.showError(wc_pmm_ajax.strings.error);
                    callback();
                }
            });
        },
        
        addMediaItem: function(mediaData) {
            // Use filename as default SKU (remove file extension)
            let defaultSku = mediaData.filename.replace(/\.[^/.]+$/, "");
            
            let newItem = {
                attachment_id: mediaData.attachment_id,
                original_url: mediaData.url,
                watermark_id: null,
                watermark_url: null,
                filename: mediaData.filename,
                filesize: mediaData.filesize,
                sku: defaultSku
            };
            
            this.mediaData.push(newItem);
            this.renderMediaTable();
            this.updateMediaDataField();
            
            // Auto-generate watermark for new images
            let newIndex = this.mediaData.length - 1;
            this.autoGenerateWatermark(newIndex);
        },
        
        renderMediaTable: function() {
            // First, save current SKU values from input fields before re-rendering
            this.saveCurrentSKUs();
            
            let tbody = $('#wc-pmm-media-tbody');
            tbody.empty();
            
            if (this.mediaData.length === 0) {
                tbody.append(`
                    <tr class="wc-pmm-no-media">
                        <td colspan="5" class="wc-pmm-no-media-text">
                            ${wc_pmm_ajax.strings.drag_drop_here}
                        </td>
                    </tr>
                `);
                return;
            }
            
            this.mediaData.forEach((item, index) => {
                let row = this.createMediaRow(item, index);
                tbody.append(row);
            });
            
            // Re-initialize sortable
            this.initSortable();
        },
        
        saveCurrentSKUs: function() {
            // Save current SKU values from input fields
            $('.wc-pmm-sku-input').each(function() {
                let index = $(this).closest('tr').data('index');
                let sku = $(this).val();
                
                if (wcPMM.mediaData[index] && sku) {
                    wcPMM.mediaData[index].sku = sku;
                }
            });
        },
        
        createMediaRow: function(media, index) {
            let watermarkHtml = '';
            
            if (media.watermark_url) {
                watermarkHtml = `
                    <img src="${media.watermark_url}" alt="Watermarked" class="wc-pmm-thumbnail">
                    <span class="wc-pmm-status wc-pmm-status-ready">Ready</span>
                `;
            } else {
                watermarkHtml = `
                    <div class="wc-pmm-watermark-placeholder">
                        <span class="dashicons dashicons-image-filter"></span>
                        <button type="button" class="button button-small wc-pmm-generate-watermark" data-index="${index}">
                            Generate Watermark
                        </button>
                    </div>
                `;
            }
            
            // Use filename as default SKU if no SKU is set
            let displaySku = media.sku || media.filename.replace(/\.[^/.]+$/, "");
            
            return `
                <tr class="wc-pmm-media-row" data-index="${index}">
                    <td class="wc-pmm-sort-handle">
                        <span class="dashicons dashicons-menu"></span>
                        <span class="wc-pmm-order-number">${index + 1}</span>
                    </td>
                    <td class="wc-pmm-original-image">
                        <div class="wc-pmm-image-preview">
                            <img src="${media.original_url}" alt="Original" class="wc-pmm-thumbnail">
                            <div class="wc-pmm-image-info">
                                <span class="wc-pmm-filename">${media.filename}</span>
                                <span class="wc-pmm-filesize">${media.filesize}</span>
                            </div>
                        </div>
                    </td>
                    <td class="wc-pmm-watermark-image">
                        <div class="wc-pmm-image-preview">
                            ${watermarkHtml}
                        </div>
                    </td>
                    <td class="wc-pmm-image-sku">
                        <input type="text" class="wc-pmm-sku-input" value="${displaySku}" placeholder="Enter SKU">
                    </td>
                    <td class="wc-pmm-actions">
                        <button type="button" class="button button-small wc-pmm-delete-image" data-index="${index}" title="Delete Image">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </td>
                </tr>
            `;
        },
        
        generateWatermark: function(e) {
            e.preventDefault();
            let index = $(e.currentTarget).data('index');
            let mediaItem = this.mediaData[index];
            
            if (!mediaItem) return;
            
            let button = $(e.currentTarget);
            let originalText = button.text();
            
            button.prop('disabled', true).text(wc_pmm_ajax.strings.processing);
            
            $.ajax({
                url: wc_pmm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_pmm_generate_watermark',
                    attachment_id: mediaItem.attachment_id,
                    nonce: wc_pmm_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.mediaData[index].watermark_id = response.data.watermark_id;
                        this.mediaData[index].watermark_url = response.data.watermark_url;
                        this.renderMediaTable();
                        this.updateMediaDataField();
                    } else {
                        this.showError(response.data);
                        button.prop('disabled', false).text(originalText);
                    }
                },
                error: () => {
                    this.showError(wc_pmm_ajax.strings.error);
                    button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        autoGenerateWatermark: function(index) {
            let mediaItem = this.mediaData[index];
            
            if (!mediaItem || mediaItem.watermark_id) return;
            
            // Add a slight delay to allow the table to render first
            setTimeout(() => {
                $.ajax({
                    url: wc_pmm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wc_pmm_generate_watermark',
                        attachment_id: mediaItem.attachment_id,
                        nonce: wc_pmm_ajax.nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            this.mediaData[index].watermark_id = response.data.watermark_id;
                            this.mediaData[index].watermark_url = response.data.watermark_url;
                            this.renderMediaTable();
                            this.updateMediaDataField();
                        }
                    },
                    error: () => {
                        // Silently fail for auto-generation, user can manually retry
                    }
                });
            }, 500);
        },
        
        bulkGenerateWatermarks: function(e) {
            e.preventDefault();
            
            console.log('Bulk watermark generation started');
            
            let itemsToProcess = [];
            
            this.mediaData.forEach((item, index) => {
                if (!item.watermark_id) {
                    itemsToProcess.push(index);
                }
            });
            
            console.log('Items to process:', itemsToProcess.length);
            
            if (itemsToProcess.length === 0) {
                this.showSuccess('All images already have watermarks!');
                return;
            }
            
            let processed = 0;
            let total = itemsToProcess.length;
            let button = $('#wc-pmm-bulk-watermark');
            
            button.prop('disabled', true).text(`Processing... (0/${total})`);
            
            // Process items one by one with delay
            let processNext = (currentIndex) => {
                if (currentIndex >= itemsToProcess.length) {
                    // All done
                    this.renderMediaTable();
                    this.updateMediaDataField();
                    button.prop('disabled', false).text('Generate All Watermarks');
                    this.showSuccess(`Generated watermarks for ${processed} images!`);
                    return;
                }
                
                let index = itemsToProcess[currentIndex];
                let mediaItem = this.mediaData[index];
                
                console.log('Processing item:', index, mediaItem);
                
                $.ajax({
                    url: wc_pmm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wc_pmm_generate_watermark',
                        attachment_id: mediaItem.attachment_id,
                        nonce: wc_pmm_ajax.nonce
                    },
                    success: (response) => {
                        processed++;
                        console.log('Watermark generated for item:', index, response);
                        
                        if (response.success) {
                            this.mediaData[index].watermark_id = response.data.watermark_id;
                            this.mediaData[index].watermark_url = response.data.watermark_url;
                        }
                        
                        button.text(`Processing... (${processed}/${total})`);
                        
                        // Process next item after delay
                        setTimeout(() => {
                            processNext(currentIndex + 1);
                        }, 1000);
                    },
                    error: (xhr, status, error) => {
                        processed++;
                        console.error('Error generating watermark for item:', index, error);
                        
                        button.text(`Processing... (${processed}/${total})`);
                        
                        // Process next item after delay even if this one failed
                        setTimeout(() => {
                            processNext(currentIndex + 1);
                        }, 1000);
                    }
                });
            };
            
            // Start processing
            processNext(0);
        },
        
        deleteImage: function(e) {
            e.preventDefault();
            
            if (!confirm(wc_pmm_ajax.strings.confirm_delete)) {
                return;
            }
            
            let index = $(e.currentTarget).data('index');
            let mediaItem = this.mediaData[index];
            
            if (!mediaItem) return;
            
            // Remove from array
            this.mediaData.splice(index, 1);
            
            // Re-render table
            this.renderMediaTable();
            this.updateMediaDataField();
            
            // Optionally delete from server
            $.ajax({
                url: wc_pmm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_pmm_delete_image',
                    attachment_id: mediaItem.attachment_id,
                    watermark_id: mediaItem.watermark_id || 0,
                    nonce: wc_pmm_ajax.nonce
                }
            });
        },
        
        updateSKU: function(e) {
            let index = $(e.currentTarget).closest('tr').data('index');
            let sku = $(e.currentTarget).val();
            
            if (this.mediaData[index]) {
                this.mediaData[index].sku = sku;
                this.updateMediaDataField();
            }
        },
        
        updateOrder: function() {
            let newOrder = [];
            
            $('#wc-pmm-media-tbody tr').each(function() {
                let index = $(this).data('index');
                if (index !== undefined && wcPMM.mediaData[index]) {
                    newOrder.push(wcPMM.mediaData[index]);
                }
            });
            
            this.mediaData = newOrder;
            this.renderMediaTable();
            this.updateMediaDataField();
        },
        
        updateMediaDataField: function() {
            $('#wc-pmm-media-data').val(JSON.stringify(this.mediaData));
        },
        
        saveMediaData: function() {
            this.updateMediaDataField();
        },
        
        openMediaLibrary: function(e) {
            e.preventDefault();
            
            if (wp.media) {
                // Use WordPress media library
                let mediaFrame = wp.media({
                    title: wc_pmm_ajax.strings.select_images,
                    multiple: true,
                    library: {
                        type: 'image'
                    }
                });
                
                mediaFrame.on('select', () => {
                    let attachments = mediaFrame.state().get('selection').toJSON();
                    
                    attachments.forEach(attachment => {
                        // Use filename as default SKU (remove file extension)
                        let defaultSku = attachment.filename.replace(/\.[^/.]+$/, "");
                        
                        let newItem = {
                            attachment_id: attachment.id,
                            original_url: attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url,
                            watermark_id: null,
                            watermark_url: null,
                            filename: attachment.filename,
                            filesize: this.formatFileSize(attachment.filesizeInBytes),
                            sku: defaultSku
                        };
                        
                        let newIndex = this.mediaData.length;
                        this.mediaData.push(newItem);
                        
                        // Auto-generate watermark for new images from media library
                        this.autoGenerateWatermark(newIndex);
                    });
                    
                    this.renderMediaTable();
                    this.updateMediaDataField();
                });
                
                mediaFrame.open();
            }
        },
        
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            let k = 1024;
            let sizes = ['Bytes', 'KB', 'MB', 'GB'];
            let i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        showProgress: function() {
            $('#wc-pmm-progress').show();
            this.updateProgress(0);
        },
        
        hideProgress: function() {
            $('#wc-pmm-progress').hide();
            this.updateProgress(0);
        },
        
        updateProgress: function(percent) {
            $('.wc-pmm-progress-fill').css('width', percent + '%');
            $('.wc-pmm-progress-text').text(Math.round(percent) + '%');
        },
        
        showError: function(message) {
            // Create a simple notice
            let notice = $(`
                <div class="notice notice-error is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $('.wc-pmm-container').before(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notice.fadeOut(() => notice.remove());
            }, 5000);
            
            // Manual dismiss
            notice.find('.notice-dismiss').on('click', () => {
                notice.fadeOut(() => notice.remove());
            });
        },
        
        showSuccess: function(message) {
            let notice = $(`
                <div class="notice notice-success is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $('.wc-pmm-container').before(notice);
            
            setTimeout(() => {
                notice.fadeOut(() => notice.remove());
            }, 3000);
            
            notice.find('.notice-dismiss').on('click', () => {
                notice.fadeOut(() => notice.remove());
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(() => {
        wcPMM.init();
    });
    
    // Make wcPMM globally available
    window.wcPMM = wcPMM;
    
})(jQuery);