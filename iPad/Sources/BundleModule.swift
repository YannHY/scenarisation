import Foundation

/// Sur Mac, Swift Package Manager fournit `Bundle.module` pour les ressources du paquet.
/// Dans l’app iPad, ces ressources (catalogues JSON, dossier Web) sont copiées dans le paquet de l’app.
extension Bundle {
    static var module: Bundle { .main }
}
