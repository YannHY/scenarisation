import SwiftUI

/// Champ de recherche du site, en tête de la barre latérale, avec les filtres par catégorie du site.
struct SiteSearchBar: View {
    @ObservedObject var model: SiteSearchModel
    var focused: FocusState<Bool>.Binding

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack(spacing: 6) {
                Image(systemName: "magnifyingglass").foregroundStyle(.secondary).accessibilityHidden(true)
                TextField("Rechercher", text: $model.query)
                    .textFieldStyle(.plain)
                    .focused(focused)
                    .autocorrectionDisabled()
                    .onSubmit { focused.wrappedValue = true }
                    .onKeyPress(.escape) { dismiss(); return .handled }
                    .accessibilityLabel("Rechercher sur scenarisation.eu : aide, modèles, scénarios partagés…")
                if !model.query.isEmpty {
                    Button { dismiss() } label: { Image(systemName: "xmark.circle.fill").foregroundStyle(.secondary) }
                        .buttonStyle(.plain).help("Effacer la recherche").accessibilityLabel("Effacer la recherche")
                }
            }
            .padding(.horizontal, 9).padding(.vertical, 6)
            .background(StudioStyle.field, in: RoundedRectangle(cornerRadius: 8, style: .continuous))
            .help("Aide, modèles, scénarios partagés, compétences, prompts et références de scenarisation.eu (⌥⌘F)")
            if focused.wrappedValue || model.isActive {
                FlowLayout(spacing: 5) {
                    ForEach(SiteSearchCategory.allCases) { category in filter(category) }
                }
                .accessibilityElement(children: .contain)
                .accessibilityLabel("Filtrer par catégorie")
                .transition(.opacity)
            }
        }
        .padding(.horizontal, 12).padding(.top, 6).padding(.bottom, 4)
        .animation(.snappy(duration: 0.2), value: focused.wrappedValue || model.isActive)
    }

    private func filter(_ category: SiteSearchCategory) -> some View {
        let selected = model.category == category
        return Button { model.category = category } label: {
            Text(category.label).font(.caption.weight(selected ? .semibold : .regular))
                .padding(.horizontal, 8).padding(.vertical, 3)
                .foregroundStyle(selected ? Color.white : Color.primary)
                .background(selected ? Color.accentColor : StudioStyle.field, in: Capsule())
                .contentShape(Capsule())
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(selected ? .isSelected : [])
    }

    private func dismiss() {
        model.clear()
        focused.wrappedValue = false
    }
}

/// Résultats affichés à la place des sections de la barre latérale pendant une recherche.
struct SiteSearchResults: View {
    @ObservedObject var model: SiteSearchModel

    var body: some View {
        Section {
            HStack(spacing: 6) {
                if model.status == .searching { ProgressView().controlSize(.small) }
                Text(model.statusText).font(.callout).foregroundStyle(.secondary)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .accessibilityElement(children: .combine)
            ForEach(model.results) { result in
                Button { Platform.open(result.url) } label: { row(result) }
                    .buttonStyle(.plain)
                    .help(result.url.absoluteString)
                    .contextMenu {
                        Button("Ouvrir dans le navigateur") { Platform.open(result.url) }
                        ShareLink("Partager le lien", item: result.url)
                    }
            }
            if model.canLoadMore {
                Button { model.loadMore() } label: {
                    HStack(spacing: 6) {
                        if model.loadingMore { ProgressView().controlSize(.small) }
                        Text("Afficher plus de résultats")
                    }
                }
                .buttonStyle(.borderless).disabled(model.loadingMore)
            }
        } header: {
            Text("Recherche sur le site")
        }
    }

    private func row(_ result: SiteSearchResult) -> some View {
        VStack(alignment: .leading, spacing: 3) {
            Text(result.category).font(.caption2.weight(.semibold)).foregroundStyle(.tint).textCase(.uppercase)
            Text(result.title).font(.callout.weight(.semibold)).lineLimit(2)
            let excerpt = SiteSearch.excerpt(result.excerpt)
            if !excerpt.characters.isEmpty {
                Text(excerpt).font(.caption).foregroundStyle(.secondary).lineLimit(3)
            }
        }
        .padding(.vertical, 3)
        .frame(maxWidth: .infinity, alignment: .leading)
        .contentShape(Rectangle())
        .accessibilityElement(children: .combine)
        .accessibilityHint("Ouvre la page sur scenarisation.eu")
    }
}
