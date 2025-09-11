<#
PowerShell script for initializing and pushing two repositories (coursier_local and coursier_prod) to GitHub.
Usage:
  Open PowerShell as Administrator and run:
    .\setup_github_repos.ps1 -GitHubUser "YourUser" -GitHubToken "YourPAT"

Parameters:
  - GitHubUser: Your GitHub username
  - GitHubToken: A GitHub Personal Access Token with repo permissions

The script will:
  - Ensure Git and GitHub CLI (gh) are installed (via winget)
  - Initialize a local Git repo if missing
  - Make an initial commit
  - Create the remote GitHub repo if it doesn’t exist
  - Add remote origin and push to main branch

#>
param(
    [Parameter(Mandatory=$true)] [string]$GitHubUser,
    [Parameter(Mandatory=$true)] [string]$GitHubToken
)

# List of paths to version
$paths = @(
    "$PSScriptRoot",
    "${PSScriptRoot.Replace('COURSIER_LOCAL','coursier_prod')}"
)

function Ensure-Git {
    if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
        Write-Host "Installing Git..." -ForegroundColor Yellow
        winget install --id Git.Git -e --source winget -h | Out-Null
    }
}

function Ensure-GhCli {
    if (-not (Get-Command gh -ErrorAction SilentlyContinue)) {
        Write-Host "Installing GitHub CLI..." -ForegroundColor Yellow
        winget install --id GitHub.cli -e --source winget -h | Out-Null
    }
}

# Install prerequisites
Ensure-Git
Ensure-GhCli

foreach ($path in $paths) {
    if (-not (Test-Path $path)) {
        Write-Warning "Path not found: $path"
        continue
    }
    Write-Host "Processing $path..." -ForegroundColor Cyan
    Push-Location $path

    # Initialize Git if needed
    if (-not (Test-Path ".git")) {
        git init | Out-Null
        Write-Host "Init Git repo" -ForegroundColor Green
    }

    # Commit all
    git add .
    git commit -m "Initial commit for $(Split-Path -Leaf $path)" -q
    
    # Determine repo name
    $repoName = Split-Path -Leaf $path

    # Create remote via gh
    if (Get-Command gh -ErrorAction SilentlyContinue) {
        $exists = gh repo view "$GitHubUser/$repoName" --json name --jq ".name" 2>$null
        if (-not $exists) {
            Write-Host "Creating remote repo $GitHubUser/$repoName on GitHub..."
            gh repo create "$GitHubUser/$repoName" --public --confirm | Out-Null
        } else {
            Write-Host "Remote repo already exists." -ForegroundColor Yellow
        }
    }

    # Configure remote and push
    $remoteUrl = "https://${GitHubUser}:${GitHubToken}@github.com/${GitHubUser}/${repoName}.git"
    git remote remove origin 2>$null
    git remote add origin $remoteUrl
    git branch -M main
    git push -u origin main

    Pop-Location
    Write-Host "Done for $path`n" -ForegroundColor Green
}

Write-Host "All done. Your projects are now on GitHub." -ForegroundColor Magenta
