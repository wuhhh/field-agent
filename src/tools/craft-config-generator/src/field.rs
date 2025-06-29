use serde::{Deserialize, Serialize};
use indexmap::IndexMap;
use uuid::Uuid;

#[derive(Debug, Serialize, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct Field {
    pub column_suffix: Option<String>,
    pub handle: String,
    pub instructions: Option<String>,
    pub name: String,
    pub searchable: bool,
    pub settings: FieldSettings,
    pub translation_key_format: Option<String>,
    pub translation_method: String,
    #[serde(rename = "type")]
    pub field_type: String,
}

#[derive(Debug, Serialize, Deserialize)]
#[serde(untagged)]
pub enum FieldSettings {
    PlainText(PlainTextSettings),
    Assets(AssetSettings),
    CKEditor(CKEditorSettings),
    Number(NumberSettings),
    Url(UrlSettings),
    Dropdown(DropdownSettings),
    Radio(RadioSettings),
    Checkboxes(CheckboxesSettings),
    Date(DateSettings),
}

#[derive(Debug, Serialize, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct PlainTextSettings {
    pub byte_limit: Option<u32>,
    pub char_limit: Option<u32>,
    pub code: bool,
    pub initial_rows: u32,
    pub multiline: bool,
    pub placeholder: Option<String>,
    pub ui_mode: String,
}

#[derive(Debug, Serialize, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct AssetSettings {
    pub allow_self_relations: bool,
    pub allow_subfolders: bool,
    pub allow_uploads: bool,
    pub allowed_kinds: Option<Vec<String>>,
    pub branch_limit: Option<u32>,
    pub default_placement: String,
    pub default_upload_location_source: String,
    pub default_upload_location_subpath: String,
    pub maintain_hierarchy: bool,
    pub max_relations: Option<u32>,
    pub min_relations: Option<u32>,
    pub preview_mode: String,
    pub restrict_files: bool,
    pub restrict_location: bool,
    pub restricted_default_upload_subpath: Option<String>,
    pub restricted_location_source: String,
    pub restricted_location_subpath: Option<String>,
    pub selection_label: Option<String>,
    pub show_cards_in_grid: bool,
    pub show_site_menu: bool,
    pub show_unpermitted_files: bool,
    pub show_unpermitted_volumes: bool,
    pub sources: String,
    pub target_site_id: Option<String>,
    pub validate_related_elements: bool,
    pub view_mode: String,
}

#[derive(Debug, Serialize, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct CKEditorSettings {
    pub available_transforms: String,
    pub available_volumes: String,
    pub character_limit: Option<u32>,
    pub cke_config: String,
    pub create_button_label: Option<String>,
    pub default_transform: Option<String>,
    pub expand_entry_buttons: bool,
    pub full_graphql_data: bool,
    pub parse_embeds: bool,
    pub purifier_config: Option<String>,
    pub purify_html: bool,
    pub show_unpermitted_files: bool,
    pub show_unpermitted_volumes: bool,
    pub show_word_count: bool,
    pub source_editing_groups: Vec<String>,
    pub word_limit: Option<u32>,
}

#[derive(Debug, Serialize, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct NumberSettings {
    pub decimals: u32,
    pub default_value: Option<f64>,
    pub max: Option<f64>,
    pub min: Option<f64>,
    pub prefix: Option<String>,
    pub preview_currency: Option<String>,
    pub preview_format: String,
    pub size: Option<u32>,
    pub suffix: Option<String>,
}

#[derive(Debug, Serialize, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct UrlSettings {
    pub max_length: u32,
    pub placeholder: Option<String>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct DropdownSettings {
    pub options: Vec<SelectOption>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct RadioSettings {
    pub options: Vec<SelectOption>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct CheckboxesSettings {
    pub options: Vec<SelectOption>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct SelectOption {
    pub label: String,
    pub value: String,
    pub default: bool,
}

#[derive(Debug, Serialize, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct DateSettings {
    pub max: Option<String>,
    pub min: Option<String>,
    pub minute_increment: u32,
    pub show_date: bool,
    pub show_time: bool,
    pub show_timezone: bool,
}

impl Field {
    pub fn new_plain_text(name: &str, handle: &str, instructions: Option<&str>, multiline: bool) -> Self {
        Field {
            column_suffix: None,
            handle: handle.to_string(),
            instructions: instructions.map(|s| s.to_string()),
            name: name.to_string(),
            searchable: false,
            settings: FieldSettings::PlainText(PlainTextSettings {
                byte_limit: None,
                char_limit: None,
                code: false,
                initial_rows: if multiline { 4 } else { 1 },
                multiline,
                placeholder: None,
                ui_mode: "normal".to_string(),
            }),
            translation_key_format: None,
            translation_method: "none".to_string(),
            field_type: "craft\\fields\\PlainText".to_string(),
        }
    }

    pub fn new_rich_text(name: &str, handle: &str, instructions: Option<&str>) -> Self {
        Field {
            column_suffix: None,
            handle: handle.to_string(),
            instructions: instructions.map(|s| s.to_string()),
            name: name.to_string(),
            searchable: false,
            settings: FieldSettings::CKEditor(CKEditorSettings {
                available_transforms: "".to_string(),
                available_volumes: "".to_string(),
                character_limit: None,
                cke_config: Uuid::new_v4().to_string(),
                create_button_label: None,
                default_transform: None,
                expand_entry_buttons: false,
                full_graphql_data: true,
                parse_embeds: false,
                purifier_config: None,
                purify_html: true,
                show_unpermitted_files: false,
                show_unpermitted_volumes: false,
                show_word_count: false,
                source_editing_groups: vec!["__ADMINS__".to_string()],
                word_limit: None,
            }),
            translation_key_format: None,
            translation_method: "none".to_string(),
            field_type: "craft\\ckeditor\\Field".to_string(),
        }
    }

    pub fn new_image(name: &str, handle: &str, instructions: Option<&str>) -> Self {
        let volume_uid = Uuid::new_v4().to_string();
        Field {
            column_suffix: None,
            handle: handle.to_string(),
            instructions: instructions.map(|s| s.to_string()),
            name: name.to_string(),
            searchable: false,
            settings: FieldSettings::Assets(AssetSettings {
                allow_self_relations: false,
                allow_subfolders: false,
                allow_uploads: true,
                allowed_kinds: Some(vec!["image".to_string()]),
                branch_limit: None,
                default_placement: "end".to_string(),
                default_upload_location_source: format!("volume:{}", volume_uid),
                default_upload_location_subpath: "images".to_string(),
                maintain_hierarchy: false,
                max_relations: Some(1),
                min_relations: None,
                preview_mode: "full".to_string(),
                restrict_files: true,
                restrict_location: false,
                restricted_default_upload_subpath: None,
                restricted_location_source: format!("volume:{}", volume_uid),
                restricted_location_subpath: None,
                selection_label: None,
                show_cards_in_grid: false,
                show_site_menu: false,
                show_unpermitted_files: false,
                show_unpermitted_volumes: false,
                sources: "*".to_string(),
                target_site_id: None,
                validate_related_elements: false,
                view_mode: "list".to_string(),
            }),
            translation_key_format: None,
            translation_method: "none".to_string(),
            field_type: "craft\\fields\\Assets".to_string(),
        }
    }

    pub fn new_number(name: &str, handle: &str, instructions: Option<&str>) -> Self {
        Field {
            column_suffix: None,
            handle: handle.to_string(),
            instructions: instructions.map(|s| s.to_string()),
            name: name.to_string(),
            searchable: false,
            settings: FieldSettings::Number(NumberSettings {
                decimals: 0,
                default_value: None,
                max: None,
                min: None,
                prefix: None,
                preview_currency: None,
                preview_format: "decimal".to_string(),
                size: None,
                suffix: None,
            }),
            translation_key_format: None,
            translation_method: "none".to_string(),
            field_type: "craft\\fields\\Number".to_string(),
        }
    }

    pub fn new_url(name: &str, handle: &str, instructions: Option<&str>) -> Self {
        Field {
            column_suffix: None,
            handle: handle.to_string(),
            instructions: instructions.map(|s| s.to_string()),
            name: name.to_string(),
            searchable: false,
            settings: FieldSettings::Url(UrlSettings {
                max_length: 255,
                placeholder: None,
            }),
            translation_key_format: None,
            translation_method: "none".to_string(),
            field_type: "craft\\fields\\Url".to_string(),
        }
    }

    pub fn get_uuid(&self) -> String {
        Uuid::new_v4().to_string()
    }
}