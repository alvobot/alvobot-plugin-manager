# AlvoBot Pro - Sistema de Design Unificado

Este documento descreve o sistema de design unificado implementado para todos os módulos do AlvoBot Pro, garantindo consistência visual e uma experiência de usuário coesa.

## 📋 Visão Geral

O sistema unificado foi criado para resolver inconsistências de design entre os módulos e estabelecer uma base sólida para futuras implementações. Todos os módulos agora seguem os mesmos padrões visuais, cores, tipografia e componentes.

## 🎨 Arquitetura CSS

### Arquivo Principal
- **`/assets/css/styles.css`** - Sistema de design unificado com variáveis CSS e componentes base

## 🔧 Variáveis CSS

### Cores Principais
```css
--alvobot-primary: #CD9042        /* Dourado principal */
--alvobot-primary-dark: #B8803A   /* Dourado escuro */
--alvobot-secondary: #0E100D      /* Preto secundário */
--alvobot-accent: #2271b1         /* Azul de destaque */
--alvobot-accent-dark: #135e96    /* Azul escuro */
```

### Cores de Estado
```css
--alvobot-success: #46b450        /* Verde sucesso */
--alvobot-success-bg: #edfaef     /* Fundo verde */
--alvobot-error: #dc3232          /* Vermelho erro */
--alvobot-error-bg: #fcf0f1       /* Fundo vermelho */
--alvobot-warning: #ffb900        /* Amarelo aviso */
--alvobot-warning-bg: #fcf9e8     /* Fundo amarelo */
```

### Escala de Cinzas
```css
--alvobot-white: #ffffff
--alvobot-gray-50: #f9f9f9
--alvobot-gray-100: #f6f7f7
--alvobot-gray-200: #f0f0f1
--alvobot-gray-300: #e5e5e5
--alvobot-gray-400: #dcdcde
--alvobot-gray-500: #c3c4c7
--alvobot-gray-600: #646970
--alvobot-gray-700: #50575e
--alvobot-gray-800: #2c3338
--alvobot-gray-900: #1d2327
```

### Tipografia
```css
--alvobot-font-size-xs: 12px
--alvobot-font-size-sm: 13px
--alvobot-font-size-base: 14px
--alvobot-font-size-lg: 16px
--alvobot-font-size-xl: 18px
--alvobot-font-size-2xl: 20px
--alvobot-font-size-3xl: 24px
```

### Espaçamentos
```css
--alvobot-space-xs: 4px
--alvobot-space-sm: 8px
--alvobot-space-md: 12px
--alvobot-space-lg: 16px
--alvobot-space-xl: 20px
--alvobot-space-2xl: 24px
--alvobot-space-3xl: 32px
```

## 🧱 Componentes Base

### Layout

#### Container Principal
```html
<div class="alvobot-admin-wrap">
  <div class="alvobot-admin-container">
    <!-- Conteúdo -->
  </div>
</div>
```

#### Header
```html
<div class="alvobot-admin-header">
  <h1>Título da Página</h1>
  <p>Descrição da página</p>
</div>
```

### Cards

#### Card Base
```html
<div class="alvobot-card">
  <div class="alvobot-card-header">
    <div>
      <h2 class="alvobot-card-title">Título do Card</h2>
      <p class="alvobot-card-subtitle">Subtítulo ou descrição</p>
    </div>
  </div>
  <div class="alvobot-card-content">
    <!-- Conteúdo do card -->
  </div>
  <div class="alvobot-card-footer">
    <!-- Ações/botões -->
  </div>
</div>
```

### Grid System

#### Grid Responsivo
```html
<div class="alvobot-grid alvobot-grid-auto">
  <!-- Cards automaticamente responsivos -->
</div>

<div class="alvobot-grid alvobot-grid-2">
  <!-- 2 colunas fixas -->
</div>

<div class="alvobot-grid alvobot-grid-3">
  <!-- 3 colunas fixas -->
</div>
```

### Formulários

#### Tabela de Formulário
```html
<table class="alvobot-form-table">
  <tr>
    <th>Label do Campo</th>
    <td>
      <input type="text" class="alvobot-input" />
      <p class="alvobot-description">Texto de ajuda</p>
    </td>
  </tr>
</table>
```

#### Campos de Entrada
```html
<!-- Input de texto -->
<input type="text" class="alvobot-input" />

<!-- Input pequeno -->
<input type="text" class="alvobot-input alvobot-input-sm" />

<!-- Input grande -->
<input type="text" class="alvobot-input alvobot-input-lg" />

<!-- Textarea -->
<textarea class="alvobot-textarea"></textarea>

<!-- Select -->
<select class="alvobot-select"></select>
```

### Botões

#### Variações de Botão
```html
<!-- Botão primário -->
<button class="alvobot-btn alvobot-btn-primary">Salvar</button>

<!-- Botão secundário -->
<button class="alvobot-btn alvobot-btn-secondary">Cancelar</button>

<!-- Botão outline -->
<button class="alvobot-btn alvobot-btn-outline">Editar</button>

<!-- Botão de perigo -->
<button class="alvobot-btn alvobot-btn-danger">Excluir</button>

<!-- Botão de sucesso -->
<button class="alvobot-btn alvobot-btn-success">Confirmar</button>
```

#### Tamanhos de Botão
```html
<!-- Pequeno -->
<button class="alvobot-btn alvobot-btn-primary alvobot-btn-sm">Pequeno</button>

<!-- Normal (padrão) -->
<button class="alvobot-btn alvobot-btn-primary">Normal</button>

<!-- Grande -->
<button class="alvobot-btn alvobot-btn-primary alvobot-btn-lg">Grande</button>
```

#### Grupos de Botões
```html
<div class="alvobot-btn-group">
  <button class="alvobot-btn alvobot-btn-primary">Salvar</button>
  <button class="alvobot-btn alvobot-btn-outline">Cancelar</button>
</div>

<!-- Centralizado -->
<div class="alvobot-btn-group alvobot-btn-group-centered">
  <!-- Botões -->
</div>

<!-- Alinhado à direita -->
<div class="alvobot-btn-group alvobot-btn-group-right">
  <!-- Botões -->
</div>
```

### Badges e Status

#### Badges
```html
<span class="alvobot-badge alvobot-badge-success">Ativo</span>
<span class="alvobot-badge alvobot-badge-error">Erro</span>
<span class="alvobot-badge alvobot-badge-warning">Aviso</span>
<span class="alvobot-badge alvobot-badge-info">Info</span>
<span class="alvobot-badge alvobot-badge-neutral">Neutro</span>
```

#### Indicadores de Status
```html
<span class="alvobot-status-indicator success"></span>
<span class="alvobot-status-indicator error"></span>
<span class="alvobot-status-indicator warning"></span>
<span class="alvobot-status-indicator info"></span>
```

### Notificações

```html
<div class="alvobot-notice alvobot-notice-success">
  <p>Operação realizada com sucesso!</p>
</div>

<div class="alvobot-notice alvobot-notice-error">
  <p>Ocorreu um erro na operação.</p>
</div>

<div class="alvobot-notice alvobot-notice-warning">
  <p>Atenção: verifique as configurações.</p>
</div>

<div class="alvobot-notice alvobot-notice-info">
  <p>Informação importante.</p>
</div>
```

### Tabelas

```html
<table class="alvobot-table">
  <thead>
    <tr>
      <th>Coluna 1</th>
      <th>Coluna 2</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Dado 1</td>
      <td>Dado 2</td>
    </tr>
  </tbody>
</table>

<!-- Tabela listrada -->
<table class="alvobot-table alvobot-table-striped">
  <!-- Conteúdo -->
</table>
```

### Toggle Switches

```html
<label class="alvobot-toggle">
  <input type="checkbox" />
  <span class="alvobot-toggle-slider"></span>
</label>
```

## 🔧 Classes Utilitárias

### Espaçamentos
```html
<!-- Margens -->
<div class="alvobot-mt-xl">Margem top XL</div>
<div class="alvobot-mb-lg">Margem bottom LG</div>

<!-- Gaps para flexbox/grid -->
<div class="alvobot-gap-md">Gap médio</div>
```

### Cores de Texto
```html
<span class="alvobot-text-primary">Texto primário</span>
<span class="alvobot-text-success">Texto de sucesso</span>
<span class="alvobot-text-error">Texto de erro</span>
<span class="alvobot-text-warning">Texto de aviso</span>
```

### Display e Layout
```html
<!-- Display -->
<div class="alvobot-flex">Flexbox</div>
<div class="alvobot-block">Block</div>
<div class="alvobot-hidden">Oculto</div>

<!-- Flex utilities -->
<div class="alvobot-justify-center">Centralizado</div>
<div class="alvobot-items-center">Itens centralizados</div>
<div class="alvobot-flex-col">Coluna</div>

<!-- Largura -->
<div class="alvobot-w-full">Largura 100%</div>
```

## 📱 Responsividade

O sistema inclui breakpoints responsivos automáticos:

- **Mobile**: até 480px
- **Tablet**: 481px - 782px  
- **Desktop**: 783px+

### Grid Responsivo
```css
.alvobot-grid-auto {
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
}

@media (max-width: 782px) {
  .alvobot-grid-2,
  .alvobot-grid-3,
  .alvobot-grid-4 {
    grid-template-columns: 1fr;
  }
}
```

### Exemplo de Migração

**Antes:**
```html
<div class="alvobot-pro-wrap">
  <div class="alvobot-pro-header">
    <h1>Título</h1>
  </div>
  <div class="alvobot-pro-card">
    <h2>Card</h2>
    <button class="button-primary">Salvar</button>
  </div>
</div>
```

**Depois:**
```html
<div class="alvobot-admin-wrap">
  <div class="alvobot-admin-header">
    <h1>Título</h1>
  </div>
  <div class="alvobot-card">
    <div class="alvobot-card-header">
      <h2 class="alvobot-card-title">Card</h2>
    </div>
    <div class="alvobot-card-footer">
      <button class="alvobot-btn alvobot-btn-primary">Salvar</button>
    </div>
  </div>
</div>
```

## 🛠 Personalização

### Sobrescrevendo Variáveis
```css
:root {
  /* Personalizar cores principais */
  --alvobot-primary: #custom-color;
  --alvobot-accent: #custom-accent;
  
  /* Personalizar espaçamentos */
  --alvobot-space-xl: 24px;
}
```

### Extensão de Componentes
```css
/* Personalizações específicas do módulo */
.meu-modulo .alvobot-card {
  border-left: 4px solid var(--alvobot-primary);
}

.meu-modulo .alvobot-btn-primary {
  background: linear-gradient(45deg, var(--alvobot-primary), var(--alvobot-primary-dark));
}
```

## ✅ Benefícios do Sistema Unificado

### Para Desenvolvedores
- **Consistência**: Todos os módulos seguem o mesmo padrão visual
- **Produtividade**: Componentes reutilizáveis aceleram o desenvolvimento
- **Manutenibilidade**: Mudanças centralizadas em um único arquivo
- **Flexibilidade**: Sistema baseado em variáveis CSS facilita customizações

### Para Usuários
- **UX Coesa**: Interface consistente em todos os módulos
- **Curva de Aprendizado**: Padrões familiares entre diferentes funcionalidades
- **Acessibilidade**: Componentes seguem padrões de acessibilidade
- **Performance**: CSS otimizado reduz o tempo de carregamento

## 📚 Recursos Adicionais

### Checklist para Novos Módulos
- [ ] Importar `styles.css`
- [ ] Usar classes unificadas para layout
- [ ] Seguir padrões de nomenclatura
- [ ] Implementar responsividade
- [ ] Testar em diferentes dispositivos
- [ ] Validar acessibilidade

### Arquivos de Referência
- `/assets/css/styles.css` - Sistema completo
- `/assets/templates/dashboard.php` - Exemplo de implementação
- `/includes/modules/*/css/admin.css` - Implementações específicas

---

**Versão:** 1.0  
**Última Atualização:** Janeiro 2025  
**Autor:** AlvoBot Pro Team