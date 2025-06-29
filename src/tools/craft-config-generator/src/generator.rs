use crate::field::{Field, FieldSettings};
use crate::entry_type::EntryType;
use crate::config::{ProjectConfig, FieldType};
use anyhow::{Result, Context};
use std::fs;
use std::path::Path;
use std::collections::HashMap;
use uuid::Uuid;
use indexmap::IndexMap;
use chrono::Utc;

pub fn generate_field(
    name: &str,
    handle: &str,
    field_type: &str,
    instructions: Option<&str>,
    required: Option<bool>,
) -> Result<Field> {
    let field = match field_type.to_lowercase().as_str() {
        "text" | "plaintext" | "plain_text" => Field::new_plain_text(name, handle, instructions, false),
        "textarea" | "multiline" => Field::new_plain_text(name, handle, instructions, true),
        "richtext" | "rich_text" | "wysiwyg" => Field::new_rich_text(name, handle, instructions),
        "image" | "asset" => Field::new_image(name, handle, instructions),
        "number" | "integer" | "float" => Field::new_number(name, handle, instructions),
        "url" | "link" => Field::new_url(name, handle, instructions),
        _ => anyhow::bail!("Unsupported field type: {}", field_type),
    };

    Ok(field)
}

pub fn generate_entry_type(
    name: &str,
    handle: &str,
    field_handles: &[String],
) -> Result<EntryType> {
    let mut entry_type = EntryType::new(name, handle);
    
    entry_type.add_title_field();
    
    for field_handle in field_handles {
        let field_uid = Uuid::new_v4().to_string();
        entry_type.add_field(&field_uid, false);
    }

    Ok(entry_type)
}

pub fn generate_from_config(config_path: &str, output_dir: &str) -> Result<()> {
    let config_content = fs::read_to_string(config_path)
        .context("Failed to read config file")?;
    
    let config: ProjectConfig = serde_json::from_str(&config_content)
        .context("Failed to parse config JSON")?;

    fs::create_dir_all(output_dir)?;
    fs::create_dir_all(format!("{}/fields", output_dir))?;
    fs::create_dir_all(format!("{}/entryTypes", output_dir))?;
    fs::create_dir_all(format!("{}/sections", output_dir))?;
    fs::create_dir_all(format!("{}/volumes", output_dir))?;

    let mut field_uuids = HashMap::new();
    let mut meta_names = IndexMap::new();

    for field_config in &config.fields {
        let field = match field_config.field_type {
            FieldType::PlainText => Field::new_plain_text(
                &field_config.name,
                &field_config.handle,
                field_config.instructions.as_deref(),
                false,
            ),
            FieldType::RichText => Field::new_rich_text(
                &field_config.name,
                &field_config.handle,
                field_config.instructions.as_deref(),
            ),
            FieldType::Image | FieldType::Asset => Field::new_image(
                &field_config.name,
                &field_config.handle,
                field_config.instructions.as_deref(),
            ),
            FieldType::Number => Field::new_number(
                &field_config.name,
                &field_config.handle,
                field_config.instructions.as_deref(),
            ),
            FieldType::Url => Field::new_url(
                &field_config.name,
                &field_config.handle,
                field_config.instructions.as_deref(),
            ),
            _ => continue,
        };

        let field_uuid = Uuid::new_v4().to_string();
        field_uuids.insert(field_config.handle.clone(), field_uuid.clone());
        meta_names.insert(field_uuid.clone(), format!("{} # {}", field_config.name, field_config.handle));

        let yaml = serde_yaml::to_string(&field)?;
        let filename = format!("{}/fields/{}--{}.yaml", output_dir, field_config.handle, field_uuid);
        fs::write(&filename, yaml)?;
        println!("Generated field: {}", filename);
    }

    for entry_type_config in &config.entry_types {
        let mut entry_type = EntryType::new(&entry_type_config.name, &entry_type_config.handle);
        
        if entry_type_config.has_title_field.unwrap_or(true) {
            entry_type.add_title_field();
        }

        for field_ref in &entry_type_config.fields {
            if let Some(field_uuid) = field_uuids.get(&field_ref.handle) {
                entry_type.add_field(field_uuid, field_ref.required.unwrap_or(false));
            }
        }

        let entry_type_uuid = Uuid::new_v4().to_string();
        meta_names.insert(entry_type_uuid.clone(), format!("{} # {}", entry_type_config.name, entry_type_config.handle));

        let yaml = serde_yaml::to_string(&entry_type)?;
        let filename = format!("{}/entryTypes/{}--{}.yaml", output_dir, entry_type_config.handle, entry_type_uuid);
        fs::write(&filename, yaml)?;
        println!("Generated entry type: {}", filename);
    }

    let project = ProjectYaml {
        date_modified: Utc::now().timestamp(),
        meta: Meta {
            names: meta_names,
        },
    };

    let yaml = serde_yaml::to_string(&project)?;
    fs::write(format!("{}/project.yaml", output_dir), yaml)?;
    println!("Generated project.yaml");

    Ok(())
}

#[derive(serde::Serialize)]
#[serde(rename_all = "camelCase")]
struct ProjectYaml {
    date_modified: i64,
    meta: Meta,
}

#[derive(serde::Serialize)]
struct Meta {
    #[serde(rename = "__names__")]
    names: IndexMap<String, String>,
}

pub fn generate_example_config(output_path: &str) -> Result<()> {
    let example = ProjectConfig::example();
    let json = serde_json::to_string_pretty(&example)?;
    fs::write(output_path, json)?;
    println!("Example config written to: {}", output_path);
    Ok(())
}