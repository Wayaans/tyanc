import assert from 'node:assert/strict';
import test from 'node:test';
import { __, registerTranslations } from '../../../resources/js/lib/translations.ts';

const app = {
    config: {
        globalProperties: {},
    },
};

test('translation helper replaces overlapping placeholders safely', () => {
    registerTranslations(app, {
        translations: {
            'Page :page of :pages': 'Page :page of :pages',
        },
    });

    assert.equal(
        __('Page :page of :pages', {
            page: '2',
            pages: '5',
        }),
        'Page 2 of 5',
    );
});

test('translation helper preserves replacement values that include dollar signs', () => {
    registerTranslations(app, {
        translations: {
            'Total: :amount': 'Total: :amount',
        },
    });

    assert.equal(
        __('Total: :amount', {
            amount: '$15.00',
        }),
        'Total: $15.00',
    );
});
