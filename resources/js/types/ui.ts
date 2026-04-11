export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type BrandProps = {
    app_name: string;
    company_legal_name: string | null;
    app_logo: string | null;
    favicon: string | null;
    login_cover_image: string | null;
};

export type ThemeProps = {
    appearance: Appearance;
    primary_color: string;
    secondary_color: string;
    border_radius: string;
    sidebar_variant: string;
    spacing_density: string;
    spacing_density_value: number;
    font_family: string;
    font_family_stack: string;
    css_variables: Record<string, string>;
};

export type UserPreferencesProps = {
    locale: string | null;
    timezone: string | null;
    appearance: Appearance | null;
    sidebar_variant: string | null;
    spacing_density: string | null;
    resolved_locale: string;
    resolved_timezone: string;
    resolved_appearance: Appearance;
    resolved_sidebar_variant: string;
    resolved_spacing_density: string;
    resolved_spacing_density_value: number;
};
