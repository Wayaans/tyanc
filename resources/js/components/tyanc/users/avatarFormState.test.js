import assert from 'node:assert/strict';
import test from 'node:test';
import {
    applyAvatarRemoval,
    applySelectedAvatar,
    mergeFormState,
} from './avatarFormState.ts';

test('mergeFormState preserves existing fields while applying a patch', () => {
    const state = {
        name: 'User Avatar',
        avatar: null,
        remove_avatar: false,
    };

    const nextState = mergeFormState(state, { name: 'Updated Avatar User' });

    assert.deepEqual(nextState, {
        name: 'Updated Avatar User',
        avatar: null,
        remove_avatar: false,
    });
    assert.notEqual(nextState, state);
});

test('applySelectedAvatar keeps the selected file and clears the remove flag in one update', () => {
    const file = new File(['avatar'], 'avatar.png', { type: 'image/png' });
    const state = {
        name: 'User Avatar',
        avatar: null,
        remove_avatar: true,
    };

    const nextState = applySelectedAvatar(state, file);

    assert.equal(nextState.avatar, file);
    assert.equal(nextState.remove_avatar, false);
    assert.equal(nextState.name, state.name);
});

test('applySelectedAvatar preserves the remove flag when no file is selected', () => {
    const state = {
        name: 'User Avatar',
        avatar: null,
        remove_avatar: true,
    };

    const nextState = applySelectedAvatar(state, null);

    assert.equal(nextState.avatar, null);
    assert.equal(nextState.remove_avatar, true);
    assert.equal(nextState.name, state.name);
});

test('applyAvatarRemoval clears the selected avatar and marks the form for removal', () => {
    const file = new File(['avatar'], 'avatar.png', { type: 'image/png' });
    const state = {
        name: 'User Avatar',
        avatar: file,
        remove_avatar: false,
    };

    const nextState = applyAvatarRemoval(state, true);

    assert.equal(nextState.avatar, null);
    assert.equal(nextState.remove_avatar, true);
    assert.equal(nextState.name, state.name);
});

test('applyAvatarRemoval can clear the remove flag without changing the current avatar state', () => {
    const state = {
        name: 'User Avatar',
        avatar: null,
        remove_avatar: true,
    };

    const nextState = applyAvatarRemoval(state, false);

    assert.equal(nextState.avatar, null);
    assert.equal(nextState.remove_avatar, false);
    assert.equal(nextState.name, state.name);
});
