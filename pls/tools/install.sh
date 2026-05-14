#!/bin/bash
#
# pls installer — inspired by Spin's install.sh (serversideup/spin)
#
# This script should be run via curl:
#   bash -c "$(curl -fsSL https://raw.githubusercontent.com/canyongbs/common/main/pls/tools/install.sh)"
# or via wget:
#   bash -c "$(wget -qO- https://raw.githubusercontent.com/canyongbs/common/main/pls/tools/install.sh)"
#
# You can tweak the install behavior by setting variables when running the script:
#   PLS_HOME=~/.pls bash install.sh
#
# Respects the following environment variables:
#   PLS_HOME - path to the pls install directory (default: $HOME/.pls)
#

set -e

# Default settings
PLS_HOME=${PLS_HOME:-$HOME/.pls}
PLS_BIN_URL="https://raw.githubusercontent.com/canyongbs/common/main/pls/bin/pls"

############################################################################################################
# Environment Prep
############################################################################################################

if [ -t 1 ]; then
  is_tty() {
    true
  }
else
  is_tty() {
    false
  }
fi

setup_color() {
  if is_tty; then
    RAINBOW="
      $(printf '\033[38;5;196m')
      $(printf '\033[38;5;202m')
      $(printf '\033[38;5;226m')
      $(printf '\033[38;5;082m')
    "
    RED=$(printf '\033[31m')
    GREEN=$(printf '\033[32m')
    YELLOW=$(printf '\033[33m')
    BLUE=$(printf '\033[34m')
    BOLD=$(printf '\033[1m')
    RESET=$(printf '\033[m')
  else
    RAINBOW=""
    RED=""
    GREEN=""
    YELLOW=""
    BLUE=""
    BOLD=""
    RESET=""
  fi
}

fmt_error() {
  printf '%sError: %s%s\n' "${BOLD}${RED}" "$*" "$RESET" >&2
}

############################################################################################################
# Installation
############################################################################################################

command_exists() {
  command -v "$@" >/dev/null 2>&1
}

install_pls() {
  if ! command_exists curl && ! command_exists wget; then
    fmt_error "curl or wget is required to install pls"
    exit 1
  fi

  echo "${BLUE}Installing pls to ${PLS_HOME}/bin/pls...${RESET}"

  mkdir -p "$PLS_HOME/bin"

  if command_exists curl; then
    curl -fsSL "$PLS_BIN_URL" -o "$PLS_HOME/bin/pls"
  elif command_exists wget; then
    wget -qO "$PLS_HOME/bin/pls" "$PLS_BIN_URL"
  fi

  chmod +x "$PLS_HOME/bin/pls"

  prompt_to_add_path
}

############################################################################################################
# PATH Configuration
############################################################################################################

prompt_to_add_path() {
  local shell_type
  shell_type=$(basename "$SHELL")

  local file
  case "$shell_type" in
    bash)
      file=~/.bash_profile
      # Some Linux systems use .bashrc instead
      if [[ ! -f "$file" ]] && [[ -f ~/.bashrc ]]; then
        file=~/.bashrc
      fi
      ;;
    zsh)
      file=~/.zshrc
      ;;
    *)
      echo "${RED}${BOLD}❌ Unable to detect shell type.${RESET}"
      echo "To add 'pls' to your path manually, add the following line to your shell's profile file:"
      echo "  export PATH=\"${PLS_HOME}/bin:\$PATH\""
      return 0
      ;;
  esac

  local path_value
  if [ "$PLS_HOME" = "$HOME/.pls" ]; then
    path_value='$HOME/.pls'
  else
    path_value=$PLS_HOME
  fi

  local grep_pattern="export PATH=\"${path_value}/bin:\$PATH\""

  if ! grep -qF "$grep_pattern" "$file" 2>/dev/null; then
    echo "pls detected your shell environment:"
    echo "👉 Shell Type: \"$shell_type\"."
    echo "👉 Shell Profile: \"$file\"."

    if [ -z "$set_path_automatically" ]; then
      read -n 1 -p "${BOLD}${YELLOW}Would you like pls to add itself to your PATH? [y/N] ${RESET}" response
      echo

      if [[ "$response" =~ ^[Yy]$ ]]; then
        set_path_automatically=1
      else
        set_path_automatically=0
      fi
    fi
  else
    echo "✅ Correct PATH detected in \"$file\"."
  fi

  if [ "$set_path_automatically" = 1 ]; then
    echo "👉 Adding pls to your PATH in \"$file\"."
    echo "export PATH=\"${path_value}/bin:\$PATH\"" >> "$file"
  fi
}

############################################################################################################
# Success Output
############################################################################################################

# shellcheck disable=SC2183
print_success() {
  local installed_version
  installed_version=$("$PLS_HOME/bin/pls" --version 2>/dev/null || echo "unknown")

  printf '\n'
  printf '%s        ____    %s  _        %s  ____    %s\n'          $RAINBOW $RESET
  printf '%s       |  _ \   %s | |       %s / ___|   %s\n'         $RAINBOW $RESET
  printf '%s       | |_) |  %s | |       %s \___ \   %s\n'         $RAINBOW $RESET
  printf '%s       |  __/   %s | |___    %s  ___) |  %s\n'         $RAINBOW $RESET
  printf '%s       |_|      %s |_____|   %s |____/   %s\n'         $RAINBOW $RESET
  printf '\n'
  printf "%s\n" "${BOLD}${GREEN}✅ pls ${installed_version} installed!${RESET}"
  printf '\n'
  printf '%s\n' "• Run ${BOLD}pls --help${RESET} to see available commands."
  printf '%s\n' "• Run ${BOLD}pls update${RESET} to update to the latest version."

  if [ "$set_path_automatically" = 1 ]; then
    printf '\n'
    printf '%s\n' "${BOLD}${YELLOW}pls was added to your PATH, but you may need to restart your terminal to start using it.${RESET}"
    printf '%s\n' "Or you can run: ${BOLD}source $file${RESET}"
  elif [ "$set_path_automatically" = 0 ]; then
    printf '\n'
    printf '%s\n' "${BOLD}${YELLOW}You will need to add pls to your PATH manually.${RESET}"
    printf '%s\n' "Add this to your shell profile:"
    printf '%s\n' "  export PATH=\"${path_value}/bin:\$PATH\""
    printf '%s\n' "Then restart your terminal."
  fi
  printf '\n'
}

############################################################################################################
# Main
############################################################################################################

main() {
  setup_color
  install_pls
  print_success
}

main "$@"
