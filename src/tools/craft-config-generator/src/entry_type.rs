use serde::{Deserialize, Serialize};
use indexmap::IndexMap;
use uuid::Uuid;
use chrono::Utc;

#[derive(Debug, Serialize, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct EntryType {
    pub color: Option<String>,
    pub field_layouts: IndexMap<String, FieldLayout>,
    pub handle: String,
    pub has_title_field: bool,
    pub icon: Option<String>,
    pub name: String,
    pub show_slug_field: bool,
    pub show_status_field: bool,
    pub slug_translation_key_format: Option<String>,
    pub slug_translation_method: String,
    pub title_format: Option<String>,
    pub title_translation_key_format: Option<String>,
    pub title_translation_method: String,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct FieldLayout {
    pub tabs: Vec<Tab>,
}

#[derive(Debug, Serialize, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct Tab {
    pub element_condition: Option<String>,
    pub elements: Vec<FieldLayoutElement>,
    pub name: String,
    pub uid: String,
    pub user_condition: Option<String>,
}

#[derive(Debug, Serialize, Deserialize)]
#[serde(untagged)]
pub enum FieldLayoutElement {
    EntryTitle(EntryTitleField),
    CustomField(CustomField),
}

#[derive(Debug, Serialize, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct EntryTitleField {
    pub autocapitalize: bool,
    pub autocomplete: bool,
    pub autocorrect: bool,
    pub class: Option<String>,
    pub date_added: String,
    pub disabled: bool,
    pub element_condition: Option<String>,
    pub id: Option<String>,
    pub include_in_cards: bool,
    pub input_type: Option<String>,
    pub instructions: Option<String>,
    pub label: Option<String>,
    pub max: Option<u32>,
    pub min: Option<u32>,
    pub name: Option<String>,
    pub orientation: Option<String>,
    pub placeholder: Option<String>,
    pub provides_thumbs: bool,
    pub readonly: bool,
    pub required: bool,
    pub size: Option<u32>,
    pub step: Option<u32>,
    pub tip: Option<String>,
    pub title: Option<String>,
    #[serde(rename = "type")]
    pub element_type: String,
    pub uid: String,
    pub user_condition: Option<String>,
    pub warning: Option<String>,
    pub width: u32,
}

#[derive(Debug, Serialize, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct CustomField {
    pub date_added: String,
    pub edit_condition: Option<String>,
    pub element_condition: Option<String>,
    pub field_uid: String,
    pub handle: Option<String>,
    pub include_in_cards: bool,
    pub instructions: Option<String>,
    pub label: Option<String>,
    pub provides_thumbs: bool,
    pub required: bool,
    pub tip: Option<String>,
    #[serde(rename = "type")]
    pub element_type: String,
    pub uid: String,
    pub user_condition: Option<String>,
    pub warning: Option<String>,
    pub width: u32,
}

impl EntryType {
    pub fn new(name: &str, handle: &str) -> Self {
        let layout_uid = Uuid::new_v4().to_string();
        let mut field_layouts = IndexMap::new();
        
        field_layouts.insert(
            layout_uid.clone(),
            FieldLayout {
                tabs: vec![Tab {
                    element_condition: None,
                    elements: vec![],
                    name: "Content".to_string(),
                    uid: Uuid::new_v4().to_string(),
                    user_condition: None,
                }],
            },
        );

        EntryType {
            color: None,
            field_layouts,
            handle: handle.to_string(),
            has_title_field: true,
            icon: None,
            name: name.to_string(),
            show_slug_field: true,
            show_status_field: true,
            slug_translation_key_format: None,
            slug_translation_method: "none".to_string(),
            title_format: None,
            title_translation_key_format: None,
            title_translation_method: "none".to_string(),
        }
    }

    pub fn add_title_field(&mut self) -> &mut Self {
        if let Some(layout) = self.field_layouts.values_mut().next() {
            if let Some(tab) = layout.tabs.get_mut(0) {
                tab.elements.push(FieldLayoutElement::EntryTitle(EntryTitleField {
                    autocapitalize: true,
                    autocomplete: false,
                    autocorrect: true,
                    class: None,
                    date_added: Utc::now().to_rfc3339(),
                    disabled: false,
                    element_condition: None,
                    id: None,
                    include_in_cards: false,
                    input_type: None,
                    instructions: None,
                    label: None,
                    max: None,
                    min: None,
                    name: None,
                    orientation: None,
                    placeholder: None,
                    provides_thumbs: false,
                    readonly: false,
                    required: true,
                    size: None,
                    step: None,
                    tip: None,
                    title: None,
                    element_type: "craft\\fieldlayoutelements\\entries\\EntryTitleField".to_string(),
                    uid: Uuid::new_v4().to_string(),
                    user_condition: None,
                    warning: None,
                    width: 100,
                }));
            }
        }
        self
    }

    pub fn add_field(&mut self, field_uid: &str, required: bool) -> &mut Self {
        if let Some(layout) = self.field_layouts.values_mut().next() {
            if let Some(tab) = layout.tabs.get_mut(0) {
                tab.elements.push(FieldLayoutElement::CustomField(CustomField {
                    date_added: Utc::now().to_rfc3339(),
                    edit_condition: None,
                    element_condition: None,
                    field_uid: field_uid.to_string(),
                    handle: None,
                    include_in_cards: false,
                    instructions: None,
                    label: None,
                    provides_thumbs: false,
                    required,
                    tip: None,
                    element_type: "craft\\fieldlayoutelements\\CustomField".to_string(),
                    uid: Uuid::new_v4().to_string(),
                    user_condition: None,
                    warning: None,
                    width: 100,
                }));
            }
        }
        self
    }

    pub fn get_uuid(&self) -> String {
        Uuid::new_v4().to_string()
    }
}