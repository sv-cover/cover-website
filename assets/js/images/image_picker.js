import {Bulma} from 'cover-style-system/src/js';
import Cropper from 'cropperjs';
import Modal from '@vizuaalog/bulmajs/src/plugins/modal';
import {translateMatrix, validateFilesize} from './utils';


class ImagePicker {
    static parseDocument(context) {
        const elements = context.querySelectorAll('[data-image-picker]');

        Bulma.each(elements, element => {
            let cropperTemplate;

            if (element.dataset.cropperTemplate)
                cropperTemplate = context.querySelector(element.dataset.cropperTemplate);

            new ImagePicker({
                element: element,
                cropperTemplate: cropperTemplate,
                modalTitle: element.dataset.imagePickerModalTitle || 'Crop Image',
                modalCancel: element.dataset.imagePickerModalCancel || 'Cancel',
                modalSubmit: element.dataset.imagePickerModalSubmit || 'Submit',
                imageAlt: element.dataset.imagePickerAlt || 'Uploaded picture',
                acceptMimeType: element.dataset.acceptMimeType || element.accept || 'image/*',
                targetMimeType: element.dataset.targetMimeType || element.accept?.split(1)?.[0] || 'image/jpeg',
                maxFilesize: element.dataset.maxFilesize,
                sizeModalTitle: element.dataset.imagePickerSizeModalTitle || 'File too big',
                sizeModalBody: (
                    element.dataset.imagePickerSizeModalBody
                    || 'Your file is too big. Please try to upload a file no larger than __SIZE__.'
                ),
                sizeModalButton: element.dataset.imagePickerSizeModalButton || 'Try again',
                sizeModalPlaceholder: element.dataset.imagePickerSizeModalPlaceholder || '__SIZE__',
            });
        });
    }

    constructor(options) {
        this.element = options.element;
        this.cropperTemplate = options.cropperTemplate;
        this.options = options;

        this.element.accept = options.acceptMimeType;

        this.element.addEventListener('change', this.handleFileSelection.bind(this));
        document.addEventListener('keydown', this.handleKeydown.bind(this));
    }

    initCropper(url) {
        // Create modal
        this.initModal();
        // Open modal first, just in case loading cropper takes long. Show something's happening
        this.modal.open();

        // Create Image element
        const image = new Image();
        image.src = url;
        image.alt = this.options.imageAlt;

        // Init Cropper
        let cropperOptions = {
            container: this.modalBodyElement,
        };

        if (this.options.cropperTemplate)
            cropperOptions.template = this.options.cropperTemplate.cloneNode(true).innerHTML;

        this.cropper = new Cropper(image, cropperOptions);
        this.cropper.getCropperImage().addEventListener('transform', this.handleImageTransform.bind(this));

        this.initSlider();
    }

    initModal() {
        // Explicitly create the parts of the modal we want to override
        this.modalBodyElement = document.createElement('div');
        this.modalBodyElement.classList.add('modal-card-body');

        let headElement = document.createElement('div');
        headElement.classList.add('modal-card-head');

        let cardElement = document.createElement('div');
        cardElement.classList.add('modal-card');
        cardElement.appendChild(headElement);
        cardElement.appendChild(this.modalBodyElement);

        let backgroundElement = document.createElement('div');
        backgroundElement.classList.add('modal-background');

        let modalElement = document.createElement('div');
        modalElement.classList.add('modal', 'image-picker-modal');
        modalElement.appendChild(backgroundElement);
        modalElement.appendChild(cardElement);

        // Leave the rest to Bulma.
        this.modal = Bulma.create('modal', {
            title: this.options.modalTitle,
            element: modalElement,
            style: 'card',
            buttons: [
                {
                    label: this.options.modalCancel,
                    classes: ['button'],
                    onClick: () => this.modal.close(),
                },
                {
                    label: this.options.modalSubmit,
                    classes: ['button', 'is-primary'],
                    onClick: this.handleSubmit.bind(this),
                },
            ],
            onClose: this.handleModalClose.bind(this),
        });
    }

    initSlider() {
        this.sliderElement = document.createElement('input');
        this.sliderElement.classList.add('input');
        this.sliderElement.type = 'range';
        this.sliderElement.title = 'Slide to resize image';
        this.sliderElement.step = 0.05;
        this.sliderElement.setAttribute('aria-label', 'Resize image');

        this.sliderElement.addEventListener('input', this.handleSlider.bind(this));

        this.cropper.getCropperCanvas().classList.add('block');
        this.modalBodyElement.appendChild(this.sliderElement);

        // Somehow, initial value is not set correctly through the transform
        // event alone. Fix by waiting.
        setTimeout(this.updateSlider.bind(this), 100);
    }

    updateSlider() {
        const image = this.cropper.getCropperImage();
        const selection = this.cropper.getCropperSelection();

        const minScale = Math.max(
            selection.width / image.$image.naturalWidth,
            selection.height / image.$image.naturalHeight,
        );
        this.sliderElement.min = minScale;
        this.sliderElement.max = minScale + 1;
        this.sliderElement.value = image.$getTransform()[0];
    }

    /**
     * Çontrain the image scale
     */
    constrainScale(matrix) {
        const image = this.cropper.getCropperImage();
        const selection = this.cropper.getCropperSelection();

        const minScale = Math.max(
            selection.width / image.$image.naturalWidth,
            selection.height / image.$image.naturalHeight,
        );

        if (matrix[0] < minScale || matrix[3] < minScale) {
            matrix[0] = minScale;
            matrix[3] = minScale;
        }

        return matrix;
    }

    /**
     * Çontrain the image translation
     */
    constrainTranslate(matrix) {
        const canvas = this.cropper.getCropperCanvas();
        const image = this.cropper.getCropperImage();
        const selection = this.cropper.getCropperSelection();

        let oldMatrix = image.$getTransform();

        // Scale old matrix to whatever the new scale is, just in case it has changed.
        oldMatrix[0] = matrix[0];
        oldMatrix[3] = matrix[3];

        // Obtain canvas dimensions
        const canvasRect = canvas.getBoundingClientRect();

        // Obtain image dimensions, but with new scale. Use a clone to get some data.
        let imageClone = image.cloneNode();
        imageClone.style.transform = `matrix(${oldMatrix.join(', ')})`;
        imageClone.style.opacity = '0';
        canvas.appendChild(imageClone);
        const imageRect = imageClone.getBoundingClientRect();
        canvas.removeChild(imageClone);

        // Find origin. It's not always where you'd expect it.
        const offsetMatrix = translateMatrix(
            oldMatrix,
            canvasRect.x - imageRect.x,
            canvasRect.y - imageRect.y
        );

        // Determine range of allowed translations
        const minTranslation = translateMatrix(
            offsetMatrix,
            selection.width - imageRect.width + selection.x,
            selection.height - imageRect.height + selection.y,
        );
        const maxTranslation = translateMatrix(
            offsetMatrix,
            selection.x,
            selection.y,
        );

        // Clamp translate
        if (
            matrix[4] < minTranslation[4]
            || matrix[4] > maxTranslation[4]
            || matrix[5] < minTranslation[5]
            || matrix[5] > maxTranslation[5]
        ) {
            matrix[4] = Math.min(Math.max(matrix[4], minTranslation[4]), maxTranslation[4]);
            matrix[5] = Math.min(Math.max(matrix[5], minTranslation[5]), maxTranslation[5]);
        }

        return matrix;
    }

    async uploadBlob(blob) {
        // Create file
        const file = new File([blob], 'cropped.jpg');

        // Update file field
        let dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        this.element.files = dataTransfer.files;

        // Submit
        this.element.form.submit();
    }

    handleFileSelection(event) {
        event.stopPropagation();
        event.preventDefault();

        if (!event.target.files || !event.target.files[0])
            return

        const file = event.target.files[0];
        if (validateFilesize(file, this.options.maxFilesize)) {
            // Show cropper modal
            this.initCropper(URL.createObjectURL(file));
        } else {
            // Show error message if the file is too big
            let modal = Bulma.create('modal', {
                title: this.options.sizeModalTitle,
                body: this.options.sizeModalBody.replace(
                    new RegExp(this.options.sizeModalPlaceholder, 'g'),
                    this.options.maxFilesize
                ),
                style: 'card',
                buttons: [
                    {
                        label: this.options.sizeModalButton,
                        classes: ['button'],
                        onClick: () => modal.close(),
                    },
                ],
            });
            modal.open();
        }
    }

    /**
     * Constrain the selection to be within the image.
     * Note, this only handles scale and translation. No rotation or skew.
     */
    handleImageTransform(event) {
        const image = this.cropper.getCropperImage();

        // Prevent infinite recursion when not ready
        if (!image.$image.complete)
            return;

        let matrix = event.detail.matrix.slice();

        // First clamp scale
        matrix = this.constrainScale(matrix);

        // Now clamp translate
        matrix = this.constrainTranslate(matrix);

        // Recursion step. Transform manually if matrix has changed.
        if (event.detail.matrix.some((el, idx) => el != matrix[idx])) {
            event.preventDefault();
            image.$setTransform(matrix);
        } else {
            this.updateSlider();
        }
    }

    handleSlider(event) {
        const image = this.cropper.getCropperImage();
        const scale = parseFloat(event.target.value);
        // image.$zoom and image.$scale are relative, need to set absolute value
        let transform = image.$getTransform();
        transform[0] = scale;
        transform[3] = scale;
        image.$setTransform(transform);
    }

    handleKeydown(event) {
        if (!this.cropper)
            return;

        // Don't prevent normal keyboard usage
        if (event.target != this.element && ['TEXTAREA', 'INPUT'].indexOf(event.target.nodeName) !== -1)
            return;

        // Don't prevent normal keyboard shortcuts
        if (event.metaKey || event.ctrlKey)
            return;

        const image = this.cropper.getCropperImage();
        switch (event.key) {
            case "ArrowLeft":
                image.$move(-1, 0);
                break;
            case "ArrowRight":
                image.$move(1, 0);
                break;
            case "ArrowUp":
                image.$move(0, -1);
                break;
            case "ArrowDown":
                image.$move(0, 1);
                break;
            case "=":
            case "+":
                image.$zoom(0.01);
                break;
            case "-":
            case "_":
                image.$zoom(-0.01);
                break;
        }
    }

    handleModalClose() {
        this.modal.destroy();
        delete this.cropper;
    }

    async handleSubmit() {
        const image = this.cropper.getCropperImage();
        const selection = this.cropper.getCropperSelection();
        const imageRect = image.getBoundingClientRect();

        /*
        Obtain canvas at original resolution.
        NB: despite asking for the original resolution, it is not guaranteed we
        get a pixel-perfect copy of the cropped area. This is not a problem.
        */
        const canvas = await selection.$toCanvas({
            width: (selection.width / imageRect.width) * image.$image.naturalWidth,
            height: (selection.height / imageRect.height) * image.$image.naturalHeight,
        });

        // Convert to blob. Use 90% quality in case of a compressable format.
        canvas.toBlob(this.uploadBlob.bind(this), this.options.targetMimeType, .9);
    }
}

ImagePicker.parseDocument(document);
document.addEventListener('partial-content-loaded', event => ImagePicker.parseDocument(event.detail));

export default ImagePicker;
