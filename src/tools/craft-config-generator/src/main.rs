mod field;
mod entry_type;
mod config;
mod generator;

use clap::{Parser, Subcommand};
use anyhow::Result;

#[derive(Parser)]
#[command(name = "craft-config-gen")]
#[command(about = "Generate Craft CMS project config files", long_about = None)]
struct Cli {
    #[command(subcommand)]
    command: Commands,
}

#[derive(Subcommand)]
enum Commands {
    Field {
        #[arg(long)]
        name: String,
        #[arg(long)]
        handle: String,
        #[arg(long)]
        field_type: String,
        #[arg(long)]
        instructions: Option<String>,
        #[arg(long)]
        required: Option<bool>,
        #[arg(long)]
        output: Option<String>,
    },
    EntryType {
        #[arg(long)]
        name: String,
        #[arg(long)]
        handle: String,
        #[arg(long)]
        fields: Vec<String>,
        #[arg(long)]
        output: Option<String>,
    },
    Generate {
        #[arg(long)]
        config: String,
        #[arg(long)]
        output_dir: Option<String>,
    },
    Example {
        #[arg(long, default_value = "example-config.json")]
        output: String,
    },
}

fn main() -> Result<()> {
    let cli = Cli::parse();

    match &cli.command {
        Commands::Field { name, handle, field_type, instructions, required, output } => {
            let field = generator::generate_field(
                name,
                handle,
                field_type,
                instructions.as_deref(),
                *required,
            )?;
            
            let yaml = serde_yaml::to_string(&field)?;
            
            if let Some(output_path) = output {
                std::fs::write(output_path, yaml)?;
                println!("Field config written to: {}", output_path);
            } else {
                println!("{}", yaml);
            }
        }
        Commands::EntryType { name, handle, fields, output } => {
            let entry_type = generator::generate_entry_type(name, handle, fields)?;
            
            let yaml = serde_yaml::to_string(&entry_type)?;
            
            if let Some(output_path) = output {
                std::fs::write(output_path, yaml)?;
                println!("Entry type config written to: {}", output_path);
            } else {
                println!("{}", yaml);
            }
        }
        Commands::Generate { config, output_dir } => {
            let output_dir = output_dir.as_deref().unwrap_or("config/project");
            generator::generate_from_config(config, output_dir)?;
            println!("Project config files generated in: {}", output_dir);
        }
        Commands::Example { output } => {
            generator::generate_example_config(output)?;
        }
    }

    Ok(())
}
