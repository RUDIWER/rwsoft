export default {
    required(value, message) {
        return String(value ?? '').trim() !== '' || message;
    },

    min(length, value, message) {
        const text = String(value ?? '');

        if (text.trim() === '') {
            return true;
        }

        return text.length >= Number(length) || message;
    },

    max(length, value, message) {
        return String(value ?? '').length <= Number(length) || message;
    },

    integerMin(minimum, value, message) {
        return Number(value ?? 0) >= Number(minimum) || message;
    },

    integerMax(maximum, value, message) {
        return Number(value ?? 0) <= Number(maximum) || message;
    },

    slug(value, message) {
        const text = String(value ?? '').trim();

        if (text === '') {
            return true;
        }

        return /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(text) || message;
    },

    urlOrPath(value, message) {
        const text = String(value ?? '').trim();

        if (text === '') {
            return true;
        }

        return /^(\/[^\s]*|https?:\/\/[^\s]+)$/i.test(text) || message;
    },

    json(value, message) {
        const text = String(value ?? '').trim();

        if (text === '') {
            return true;
        }

        try {
            JSON.parse(text);
            return true;
        } catch {
            return message;
        }
    },
};
