import {multiplyMatrices} from 'cropperjs';

export function translateMatrix(start, x, y) {
    const [a, b, c, d] = start;
    const e = ((x * d) - (c * y)) / ((a * d) - (c * b));
    const f = ((y * a) - (b * x)) / ((a * d) - (c * b));
    return multiplyMatrices(start, [1, 0, 0, 1, e, f]);
};



const SIZE_REGEX = /^\d+([kmg]i?)?$/i;
const SIZE_UNITS = {
    k: 1000,
    ki: 1 << 10,
    m: 1000 * 1000,
    mi: 1 << 20,
    g: 1000 * 1000 * 1000,
    gi: 1 << 30,
};

export function validateFilesize(file, maxSize) {
    if (!maxSize || !SIZE_REGEX.test(maxSize))
        return true;

    const unit = maxSize.match(/\D*$/)?.[0]?.toLowerCase();
    maxSize = parseInt(maxSize);

    if (unit)
        maxSize *= SIZE_UNITS[unit];

    return file.size < maxSize;
};

export {multiplyMatrices} from 'cropperjs';
