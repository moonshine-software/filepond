import '../css/filepond.css';
import { create, registerPlugin } from 'filepond';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginFilePoster from 'filepond-plugin-file-poster';
import FilePondPluginImageCrop from 'filepond-plugin-image-crop';

const extensionToMime = {
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.png': 'image/png',
    '.gif': 'image/gif',
    '.webp': 'image/webp',
    '.svg': 'image/svg+xml',
    '.bmp': 'image/bmp',
    '.ico': 'image/x-icon',
    '.pdf': 'application/pdf',
    '.doc': 'application/msword',
    '.docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    '.xls': 'application/vnd.ms-excel',
    '.xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    '.csv': 'text/csv',
    '.txt': 'text/plain',
    '.zip': 'application/zip',
    '.rar': 'application/x-rar-compressed',
    '.mp3': 'audio/mpeg',
    '.mp4': 'video/mp4',
    '.avi': 'video/x-msvideo',
    '.mov': 'video/quicktime',
    '.json': 'application/json',
    '.xml': 'application/xml',
};

function parseAcceptedFileTypes(accept) {
    if (!accept || !accept.trim()) return null;

    const types = accept.split(',')
        .map(ext => ext.trim().toLowerCase())
        .map(ext => extensionToMime[ext] || ext)
        .filter(Boolean);

    return types.length ? types : null;
}

function getFileExtension(filename) {
    const ext = filename.slice(filename.lastIndexOf('.')).toLowerCase();
    return ext || '';
}

function detectFileType(source, type) {
    return new Promise((resolve, reject) => {
        const ext = getFileExtension(source.name);
        const detectedType = extensionToMime[ext];

        if (detectedType) {
            resolve(detectedType);
        } else if (type) {
            resolve(type);
        } else {
            reject();
        }
    });
}

document.addEventListener('alpine:init', () => {
    registerPlugin(
        FilePondPluginImagePreview,
        FilePondPluginFileValidateType,
        FilePondPluginFilePoster,
        FilePondPluginImageCrop
    );

    Alpine.data('filepond', () => ({
        files: [],
        pond: null,
        multiple: false,
        uploadedInSession: new Set(),
        processingCount: 0,
        form: null,

        get isUploading() {
            return this.processingCount > 0;
        },

        updateSubmitButton() {
            if (this.submitBtn) {
                this.submitBtn.disabled = this.isUploading;
            }
        },

        async init() {
            await this.$nextTick();

            const input = this.$refs.input;
            const dataset = input.dataset;

            const labels = dataset.labels ? JSON.parse(dataset.labels) : {};
            const acceptedFileTypes = parseAcceptedFileTypes(dataset.extensions);
            const existingFiles = dataset.files ? JSON.parse(dataset.files) : [];

            this.multiple = input.hasAttribute('multiple');

            // Initialize files array from existing files
            this.files = existingFiles.map(f => f.source);

            const serverUrl = dataset.server || '/upload';

            const options = {
                ...dataset,
                ...labels,
                ...(acceptedFileTypes && {
                    acceptedFileTypes,
                    fileValidateTypeDetectType: detectFileType,
                }),
                ...(existingFiles.length && { files: existingFiles }),
                allowMultiple: this.multiple,
                // Preview sizes
                imagePreviewHeight: dataset.previewHeight ? parseInt(dataset.previewHeight) : 100,
                imagePreviewMinHeight: dataset.previewMinHeight ? parseInt(dataset.previewMinHeight) : 44,
                imagePreviewMaxHeight: dataset.previewMaxHeight ? parseInt(dataset.previewMaxHeight) : 100,
                filePosterHeight: dataset.posterHeight ? parseInt(dataset.posterHeight) : 100,
                // Panel layout (aspectRatio only for single file mode)
                ...(!this.multiple && dataset.panelAspectRatio && { stylePanelAspectRatio: dataset.panelAspectRatio }),
                ...(dataset.compact === 'true' && { stylePanelLayout: 'compact' }),
                server: {
                    process: {
                        url: serverUrl,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        },
                        onload: (response) => {
                            const data = JSON.parse(response);
                            return data.path;
                        },
                    },
                },
                credits: false,
            };

            this.pond = create(input, options);

            // Apply grid layout for multiple files
            if (this.multiple && dataset.grid === 'true') {
                this.pond.element.classList.add('filepond--grid');
            }

            // Find parent form and block submit during upload
            this.form = this.$el.closest('form');
            this.submitBtn = this.form?.querySelector('[type="submit"]');

            if (this.form) {
                this.form.addEventListener('submit', (e) => {
                    if (this.isUploading) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                });
            }

            // Track upload start
            this.pond.on('processfilestart', () => {
                this.processingCount++;
                this.updateSubmitButton();
            });

            // Track upload end (success or error)
            this.pond.on('processfile', (error, file) => {
                this.processingCount = Math.max(0, this.processingCount - 1);
                this.updateSubmitButton();

                if (!error && file.serverId) {
                    this.uploadedInSession.add(file.serverId);

                    if (this.multiple) {
                        this.files.push(file.serverId);
                    } else {
                        this.files = [file.serverId];
                    }
                }
            });

            // Track upload abort
            this.pond.on('processfileabort', () => {
                this.processingCount = Math.max(0, this.processingCount - 1);
                this.updateSubmitButton();
            });

            this.pond.on('removefile', (error, file) => {
                if (error) return;

                const fileId = file.serverId || file.source;
                if (fileId) {
                    this.files = this.files.filter(f => f !== fileId);
                    this.uploadedInSession.delete(fileId);
                    // FilePond handles deletion via server.revert automatically
                }
            });

            // Sync hidden inputs order when files are reordered
            this.pond.on('reorderfiles', (files) => {
                this.files = files.map(file => file.serverId || file.source).filter(Boolean);
            });
        },
    }));
})

