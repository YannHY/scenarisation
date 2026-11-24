// swift-tools-version: 6.0
import PackageDescription

let package = Package(
    name: "Scenarisation",
    platforms: [.macOS(.v15)],
    products: [.executable(name: "Scenarisation", targets: ["Scenarisation"])],
    // Sparkle : mises à jour de l’app Mac distribuée hors App Store (voir AppUpdater.swift).
    dependencies: [.package(url: "https://github.com/sparkle-project/Sparkle", from: "2.9.0")],
    targets: [
        .executableTarget(
            name: "Scenarisation",
            dependencies: [.product(name: "Sparkle", package: "Sparkle")],
            // Web : KaTeX (MIT) et pages d’aide, copiés tels quels pour conserver leur arborescence.
            resources: [.process("Resources"), .copy("Web")],
            // Déploiement macOS 15, mais lié au SDK 26 : macOS applique ainsi Liquid Glass aux contrôles système.
            linkerSettings: [.unsafeFlags(["-Xlinker", "-platform_version", "-Xlinker", "macos", "-Xlinker", "15.0", "-Xlinker", "26.0",
                                           // Sparkle.framework est copié dans Contents/Frameworks par build-app.sh.
                                           "-Xlinker", "-rpath", "-Xlinker", "@executable_path/../Frameworks"])]
        ),
        .testTarget(name: "ScenarisationTests", dependencies: ["Scenarisation"], resources: [.copy("Fixtures")])
    ]
)
