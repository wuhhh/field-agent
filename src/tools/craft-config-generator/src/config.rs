use serde::{Deserialize, Serialize};

#[derive(Debug, Serialize, Deserialize)]
pub struct ProjectConfig {
    pub fields: Vec<FieldConfig>,
    pub entry_types: Vec<EntryTypeConfig>,
    pub sections: Option<Vec<SectionConfig>>,
    pub volumes: Option<Vec<VolumeConfig>>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct FieldConfig {
    pub name: String,
    pub handle: String,
    pub field_type: FieldType,
    pub instructions: Option<String>,
    pub required: Option<bool>,
    pub searchable: Option<bool>,
    pub settings: Option<serde_json::Value>,
}

#[derive(Debug, Serialize, Deserialize)]
#[serde(rename_all = "snake_case")]
pub enum FieldType {
    PlainText,
    RichText,
    Image,
    Asset,
    Number,
    Url,
    Dropdown,
    Radio,
    Checkboxes,
    Date,
    Email,
    Lightswitch,
    Color,
    Table,
    Matrix,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct EntryTypeConfig {
    pub name: String,
    pub handle: String,
    pub fields: Vec<EntryTypeField>,
    pub has_title_field: Option<bool>,
    pub title_format: Option<String>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct EntryTypeField {
    pub handle: String,
    pub required: Option<bool>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct SectionConfig {
    pub name: String,
    pub handle: String,
    pub section_type: SectionType,
    pub entry_types: Vec<String>,
    pub site_settings: Option<Vec<SiteSetting>>,
}

#[derive(Debug, Serialize, Deserialize)]
#[serde(rename_all = "snake_case")]
pub enum SectionType {
    Channel,
    Structure,
    Single,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct SiteSetting {
    pub site_handle: String,
    pub enabled_by_default: bool,
    pub has_urls: bool,
    pub uri_format: Option<String>,
    pub template: Option<String>,
}

#[derive(Debug, Serialize, Deserialize)]
pub struct VolumeConfig {
    pub name: String,
    pub handle: String,
    pub fs_handle: String,
    pub transform_fs_handle: Option<String>,
    pub transform_subpath: Option<String>,
}

impl ProjectConfig {
    pub fn example() -> Self {
        ProjectConfig {
            fields: vec![
                FieldConfig {
                    name: "Heading".to_string(),
                    handle: "heading".to_string(),
                    field_type: FieldType::PlainText,
                    instructions: Some("Enter the main heading".to_string()),
                    required: Some(false),
                    searchable: Some(true),
                    settings: None,
                },
                FieldConfig {
                    name: "Body Content".to_string(),
                    handle: "bodyContent".to_string(),
                    field_type: FieldType::RichText,
                    instructions: Some("Main content for the page".to_string()),
                    required: Some(false),
                    searchable: Some(true),
                    settings: None,
                },
                FieldConfig {
                    name: "Featured Image".to_string(),
                    handle: "featuredImage".to_string(),
                    field_type: FieldType::Image,
                    instructions: Some("Main image for the entry".to_string()),
                    required: Some(false),
                    searchable: Some(false),
                    settings: None,
                },
                FieldConfig {
                    name: "Link URL".to_string(),
                    handle: "linkUrl".to_string(),
                    field_type: FieldType::Url,
                    instructions: None,
                    required: Some(false),
                    searchable: Some(false),
                    settings: None,
                },
                FieldConfig {
                    name: "Price".to_string(),
                    handle: "price".to_string(),
                    field_type: FieldType::Number,
                    instructions: Some("Product price".to_string()),
                    required: Some(false),
                    searchable: Some(false),
                    settings: None,
                },
            ],
            entry_types: vec![
                EntryTypeConfig {
                    name: "Page".to_string(),
                    handle: "page".to_string(),
                    fields: vec![
                        EntryTypeField {
                            handle: "heading".to_string(),
                            required: Some(true),
                        },
                        EntryTypeField {
                            handle: "bodyContent".to_string(),
                            required: Some(false),
                        },
                        EntryTypeField {
                            handle: "featuredImage".to_string(),
                            required: Some(false),
                        },
                    ],
                    has_title_field: Some(true),
                    title_format: None,
                },
                EntryTypeConfig {
                    name: "Article".to_string(),
                    handle: "article".to_string(),
                    fields: vec![
                        EntryTypeField {
                            handle: "bodyContent".to_string(),
                            required: Some(true),
                        },
                        EntryTypeField {
                            handle: "featuredImage".to_string(),
                            required: Some(false),
                        },
                    ],
                    has_title_field: Some(true),
                    title_format: None,
                },
            ],
            sections: Some(vec![
                SectionConfig {
                    name: "Pages".to_string(),
                    handle: "pages".to_string(),
                    section_type: SectionType::Channel,
                    entry_types: vec!["page".to_string()],
                    site_settings: Some(vec![
                        SiteSetting {
                            site_handle: "default".to_string(),
                            enabled_by_default: true,
                            has_urls: true,
                            uri_format: Some("{slug}".to_string()),
                            template: Some("pages/_entry".to_string()),
                        },
                    ]),
                },
                SectionConfig {
                    name: "Blog".to_string(),
                    handle: "blog".to_string(),
                    section_type: SectionType::Channel,
                    entry_types: vec!["article".to_string()],
                    site_settings: Some(vec![
                        SiteSetting {
                            site_handle: "default".to_string(),
                            enabled_by_default: true,
                            has_urls: true,
                            uri_format: Some("blog/{slug}".to_string()),
                            template: Some("blog/_entry".to_string()),
                        },
                    ]),
                },
            ]),
            volumes: Some(vec![
                VolumeConfig {
                    name: "Images".to_string(),
                    handle: "images".to_string(),
                    fs_handle: "local".to_string(),
                    transform_fs_handle: None,
                    transform_subpath: None,
                },
            ]),
        }
    }
}