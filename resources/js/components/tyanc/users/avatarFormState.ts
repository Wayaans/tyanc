export type AvatarFormState = {
    avatar: File | null;
    remove_avatar: boolean;
};

export function mergeFormState<T extends object>(
    form: T,
    patch: Partial<T>,
): T {
    return {
        ...form,
        ...patch,
    };
}

export function applySelectedAvatar<T extends AvatarFormState>(
    form: T,
    file: File | null,
): T {
    return mergeFormState(form, {
        avatar: file,
        remove_avatar: file !== null ? false : form.remove_avatar,
    } as Partial<T>);
}

export function applyAvatarRemoval<T extends AvatarFormState>(
    form: T,
    checked: boolean,
): T {
    if (!checked) {
        return mergeFormState(form, { remove_avatar: false } as Partial<T>);
    }

    return mergeFormState(form, {
        avatar: null,
        remove_avatar: true,
    } as Partial<T>);
}
