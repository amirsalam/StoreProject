/**
 * The browser's own form checks ("Please fill out this field", "Please
 * include an '@'…") speak the browser's language, not the site's. This
 * replaces their messages with ours, translated into the language chosen
 * on the site (lang/{locale}.json phrases), for every form on every page.
 *
 * The checks themselves stay the browser's: required, type="email",
 * minLength/maxLength, min/max, step, pattern.
 */

type Translate = (text: string, replacements?: Record<string, string | number>) => string;

type Field = HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement;

function isField(target: EventTarget | null): target is Field {
    return target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement;
}

function messageFor(field: Field, __: Translate): string {
    const v = field.validity;
    const type = field instanceof HTMLInputElement ? field.type : field instanceof HTMLSelectElement ? 'select' : 'textarea';

    if (v.valueMissing) {
        if (type === 'checkbox') return __('Please tick this box to continue.');
        if (type === 'radio') return __('Please select one of these options.');
        if (type === 'select') return __('Please select an item in the list.');
        if (type === 'file') return __('Please select a file.');
        return __('Please fill out this field.');
    }
    if (v.typeMismatch) {
        if (type === 'email') {
            return field.value.includes('@')
                ? __('Please enter a valid email address.')
                : __('Please include an "@" in the email address. ":value" is missing an "@".', { value: field.value });
        }
        if (type === 'url') return __('Please enter a URL (for example https://example.com).');
        return __('Please enter a valid value.');
    }
    if (v.badInput) return __('Please enter a number.');
    if (v.tooShort && 'minLength' in field) {
        return __('Please use at least :min characters (you are currently using :count).', { min: field.minLength, count: field.value.length });
    }
    if (v.tooLong && 'maxLength' in field) return __('Please use no more than :max characters.', { max: field.maxLength });
    if (v.rangeUnderflow && 'min' in field) return __('Value must be greater than or equal to :min.', { min: field.min });
    if (v.rangeOverflow && 'max' in field) return __('Value must be less than or equal to :max.', { max: field.max });
    if (v.stepMismatch) return __('Please enter a valid value.');
    if (v.patternMismatch) return field.title || __('Please match the requested format.');

    return '';
}

/**
 * Install once at startup. `translate` is read on every check, so it always
 * uses the page's current language.
 */
export function installLocalizedValidation(translate: () => Translate): void {
    if (typeof document === 'undefined') return;

    // Capture phase: `invalid` doesn't bubble.
    document.addEventListener(
        'invalid',
        (event) => {
            const field = event.target;
            if (!isField(field)) return;

            // Clear our previous message first so validity reflects the value.
            field.setCustomValidity('');
            if (field.validity.valid) return;

            field.setCustomValidity(messageFor(field, translate()));
        },
        true,
    );

    // Editing the field clears the message, so the next check starts fresh.
    const reset = (event: Event) => {
        if (isField(event.target)) event.target.setCustomValidity('');
    };
    document.addEventListener('input', reset, true);
    document.addEventListener('change', reset, true);
}
