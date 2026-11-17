// swift-tools-version: 6.0
import PackageDescription

let package = Package(
    name: "Scenarisation",
    platforms: [.macOS(.v15)],
    products: [.executable(name: "Scenarisation", targets: ["Scenarisation"])],
    targets: [
        .executableTarget(
            name: "Scenarisation",
            // Web : KaTeX (MIT) et pages d’aide, copiés tels quels pour conserver leur arborescence.
            resources: [.process("Resources"), .copy("Web")],
            // Déploiement macOS 15, mais lié au SDK 26 : macOS applique ainsi Liquid Glass aux contrôles système.
            linkerSettings: [.unsafeFlags(["-Xlinker", "-platform_version", "-Xlinker", "macos", "-Xlinker", "15.0", "-Xlinker", "26.0"])]
        ),
        .testTarget(name: "ScenarisationTests", dependencies: ["Scenarisation"], resources: [.copy("Fixtures")])
    ]
)
